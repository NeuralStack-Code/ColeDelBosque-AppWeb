<?php
/**
 * Base de los models: los objetos que el controller le entrega a la vista (y al navegador como JSON).
 *
 * Flujo de lectura:  el business trae la TABLA (filas del procedure) → el model la carga → la vista imprime.
 *
 *     $clientes = Cliente::fromTable($result->table());      // tabla → lista de models
 *     $cliente  = Cliente::fromRow($row);                     // una fila → un model
 *     $cliente  = Cliente::fromForm($_POST);                  // lo que mandó un formulario → un model
 *
 * Un model declara sus campos como propiedades públicas CON TIPO. Al cargar, cada columna con el mismo
 * nombre se convierte al tipo de su propiedad, y un NULL en un campo que no lo admite toma su valor vacío
 * ('' · 0 · false). Es lo que en otros proyectos se escribe a mano por cada campo
 * (lRow["Nombre"] != DBNull.Value ? lRow["Nombre"].ToString() : ""), hecho una sola vez aquí.
 *
 * Lo que se calcula para mostrar (nombre completo, etiquetas) va como método del model, no en la vista.
 */
abstract class Model
{
    /** Tipo de cada propiedad pública, por clase (se lee una sola vez). */
    private static array $fields = [];

    /** Carga una fila en un model nuevo. */
    public static function fromRow(array $row): static
    {
        $model = new static();
        foreach (self::fields() as $name => [$type, $nullable]) {
            if (!array_key_exists($name, $row)) continue;         // la consulta no trae esa columna: queda el valor por defecto
            $value = $row[$name];
            $model->{$name} = match (true) {
                ($value === null || $value === '') && $nullable => null,
                $type === 'int'    => (int) $value,
                $type === 'float'  => (float) $value,
                $type === 'bool'   => (bool) (int) $value,
                $type === 'string' => (string) $value,
                default            => $value,
            };
        }
        $model->onLoad($row);
        return $model;
    }

    /** Carga un model con lo que mandó un formulario (los textos llegan recortados; vacío = null si el campo lo admite). */
    public static function fromForm(array $data): static
    {
        return static::fromRow(array_map(fn($value) => is_string($value) ? trim($value) : $value, $data));
    }

    /** Carga una tabla (lista de filas) en una lista de models. */
    public static function fromTable(array $table): array
    {
        return array_map(fn(array $row) => static::fromRow($row), $table);
    }

    /** Para armar lo que no es una columna directa: models anidados, campos derivados. */
    protected function onLoad(array $row): void {}

    /** [nombre => [tipo, admiteNull]] de las propiedades públicas con tipo simple. */
    private static function fields(): array
    {
        return self::$fields[static::class] ??= (function () {
            $fields = [];
            foreach ((new ReflectionClass(static::class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                $type = $property->getType();
                if ($property->isStatic() || !$type instanceof ReflectionNamedType || !$type->isBuiltin()) continue;   // los models anidados van en onLoad()
                $fields[$property->getName()] = [$type->getName(), $type->allowsNull()];
            }
            return $fields;
        })();
    }
}
