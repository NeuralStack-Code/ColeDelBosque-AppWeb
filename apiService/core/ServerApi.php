<?php
/**
 * El "server api" de los controllers: reciben de aquí todo lo que viene del business.
 *
 * El controller arma un comando (módulo, entidad, operación), le agrega sus parámetros y lo ejecuta.
 * El server api se lo comunica al business de esa entidad, el business al stored procedure, y de regreso
 * llega un Result: si hubo error (con su mensaje) y la TABLA que trajo el business.
 *
 *     $this->api->command('Portal', 'Cliente', 'Insert');               // → ClienteBusiness::insert()
 *     $this->api->addParameter('Nombre', 'S', $cliente->Nombre);
 *     $result = $this->api->execute();
 *     if (!$result->status()) { …salió bien… } else { …$result->message()… }
 *
 *     $this->api->command('Portal', 'Cliente', 'List');
 *     $result = $this->api->execute();
 *     if (!$result->status()) $clientes = Cliente::fromTable($result->table());   // la tabla se carga en models
 *
 * Tipos de parámetro:  I entero · N número con decimales · S texto · D fecha · B sí/no (1/0).
 * Un valor vacío se guarda como NULL si el campo de la entidad lo admite (?int, ?string).
 *
 * Cada parámetro llena el campo del mismo nombre en la entidad; la operación es un método público
 * del business (List → list()). Un nombre mal escrito truena al instante (LogicException).
 *
 * Varios comandos que van juntos o no van (1.5.0, el XCTransactionProtocol de Ixaya): un solo commit al final; si uno
 * falla, rollback de todos y transaction() regresa el Result que falló. Los que siguen ya no se ejecutan.
 *
 *     $result = $this->api->transaction(function (ServerApi $api) use ($pedido) {
 *         $api->command('Ventas', 'Pedido', 'Insert');   …   $id = $api->execute()->id();
 *         $api->command('Ventas', 'Partida', 'Insert');  $api->addParameter('IdPedido', 'I', $id);  …  $api->execute();
 *     });
 *     if ($result->status()) $this->failure($result);      // el que falló (y nada quedó guardado)
 *
 * Un procedure que abre su propia transacción confirmaría la de afuera (así funciona MySQL/MariaDB): Command no lo deja
 * correr dentro de transaction(). La convención para los procedures que se usen aquí: ver framework/core-sql.
 */
final class ServerApi
{
    private ?mysqli $db;
    private string $module    = '';
    private string $entity    = '';
    private string $operation = '';
    /** nombre => [tipo, valor] */
    private array $parameters = [];
    /** El último Result de esta transacción (lo que regresa transaction() si todo salió bien). */
    private ?Result $last = null;

    public function __construct(?mysqli $db) { $this->db = $db; }

    /** Inicia un comando nuevo (olvida los parámetros del anterior). */
    public function command(string $module, string $entity, string $operation): static
    {
        $this->module     = $module;
        $this->entity     = $entity;
        $this->operation  = $operation;
        $this->parameters = [];
        return $this;
    }

    public function addParameter(string $name, string $type, mixed $value): static
    {
        $this->parameters[$name] = [$type, $value];
        return $this;
    }

    /** Le pasa el comando al business y regresa lo que respondió. */
    public function execute(): Result
    {
        $name   = "{$this->module}.{$this->entity}.{$this->operation}";
        $class  = $this->entity . 'Business';
        $folder = DIRECTORY_SEPARATOR . 'business' . DIRECTORY_SEPARATOR . strtolower($this->module) . DIRECTORY_SEPARATOR;
        if (!class_exists($class) || !is_subclass_of($class, Entity::class)
            || stripos((string) (new ReflectionClass($class))->getFileName(), $folder) === false) {
            throw new LogicException("$name: no existe la entidad {$this->entity} en el módulo {$this->module}.");
        }

        $business = new $class($this->db);
        foreach ($this->parameters as $field => [$type, $value]) {
            if (!property_exists($business, $field) || !($property = new ReflectionProperty($business, $field))->isPublic()) {
                throw new LogicException("$name: la entidad no tiene el campo '$field'.");
            }
            $business->{$field} = self::convert($type, $value, $property->getType()?->allowsNull() ?? true);
        }

        $method = lcfirst($this->operation);
        if (!method_exists($business, $method) || !(new ReflectionMethod($business, $method))->isPublic()) {
            throw new LogicException("$name: la entidad no tiene la operación '{$this->operation}'.");
        }
        $returned = $business->{$method}();

        $result = $this->last = new Result($business, is_array($returned) ? $returned : []);
        if (Command::$inTransaction && $result->status()) throw new TransactionFailed($result);   // corta y deshace todo
        return $result;
    }

    /**
     * Corre $fn (recibe este ServerApi) en UNA transacción: commit si todos los comandos salen bien; si uno falla, rollback
     * de todo y regresa ese Result (status() = true). Una excepción también deshace todo y sigue su camino.
     * Dentro de otra transaction() se suma a la de afuera (un solo commit, el de la de afuera).
     */
    public function transaction(callable $fn): Result
    {
        if ($this->db === null) throw new LogicException('Una transacción necesita conexión a la base.');
        if (Command::$inTransaction) {                                   // anidada: es parte de la de afuera
            $fn($this);
            return $this->last ?? throw new LogicException('La transacción no ejecutó ningún comando.');
        }

        $this->last = null;
        $this->db->begin_transaction();
        Command::$inTransaction = true;
        try {
            $fn($this);
            if ($this->last === null) throw new LogicException('La transacción no ejecutó ningún comando.');
            Command::$inTransaction = false;
            $this->db->commit();
            return $this->last;
        } catch (TransactionFailed $failed) {
            Command::$inTransaction = false;
            $this->db->rollback();
            return $failed->result;
        } catch (Throwable $e) {
            Command::$inTransaction = false;
            try { $this->db->rollback(); } catch (Throwable $x) { /* la conexión ya no sirve: manda el error original */ }
            throw $e;
        }
    }

    /** Convierte el valor (casi siempre texto de un formulario) al tipo del parámetro. */
    private static function convert(string $type, mixed $value, bool $nullable): mixed
    {
        if (is_string($value)) $value = trim($value);
        if (($value === '' || $value === null) && $nullable) return null;
        return match ($type) {
            'I'      => (int) $value,
            'N'      => (float) $value,
            'B'      => empty($value) ? 0 : 1,
            'S', 'D' => (string) $value,
            default  => throw new LogicException("Tipo de parámetro desconocido: '$type' (usa I, N, S, D o B)."),
        };
    }
}

/**
 * Lo que el business le contesta al controller: el estado de la operación y la tabla que trajo.
 * (El equivalente de WebServiceResult: Status/Message + GetTable.)
 */
final class Result
{
    private Entity $business;
    private array $table;

    public function __construct(Entity $business, array $table)
    {
        $this->business = $business;
        $this->table    = $table;
    }

    /**
     * Estado de la operación, igual que WebServiceResult.Status: TRUE = hubo error, FALSE = todo bien.
     * Por eso el controller pregunta  if (!$result->status()) { …cargar los datos… } else { …$result->message()… }
     */
    public function status(): bool    { return $this->business->error(); }
    /** Código del error de negocio: dup, fk, notfound… */
    public function code(): string    { return $this->business->code(); }
    public function message(): string { return $this->business->message(); }
    /** Estatus HTTP que le corresponde al error (409 duplicado, 404 no encontrado…). */
    public function http(): int       { return $this->business->http(); }

    /** Las filas que trajo el business (vacía si la operación no consulta). Se cargan con Model::fromTable(). */
    public function table(): array    { return $this->table; }
    /** La primera fila, o null. */
    public function row(): ?array     { return $this->table[0] ?? null; }
    /** Id nuevo que regresa un alta (columna Id de la tabla). */
    public function id(): int         { return (int) ($this->table[0]['Id'] ?? 0); }
    /** Filas que afectó una baja (columna Afectadas de la tabla). */
    public function affected(): int   { return (int) ($this->table[0]['Afectadas'] ?? 0); }
}

/** Interna: un comando falló dentro de ServerApi::transaction(). La atrapa transaction(), que hace rollback y regresa el Result. */
final class TransactionFailed extends RuntimeException
{
    public function __construct(public readonly Result $result)
    {
        parent::__construct('Comando fallido dentro de la transacción: ' . $result->message());
    }
}
