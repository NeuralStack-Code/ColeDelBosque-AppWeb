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
 */
final class ServerApi
{
    private ?mysqli $db;
    private string $module    = '';
    private string $entity    = '';
    private string $operation = '';
    /** nombre => [tipo, valor] */
    private array $parameters = [];

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

        return new Result($business, is_array($returned) ? $returned : []);
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
