<?php
declare(strict_types=1);

namespace Serato\InvoiceQueue;

use Serato\InvoiceQueue\InvoiceValidator;
use Serato\InvoiceQueue\Exception\ValidationException;
use Serato\InvoiceQueue\Error\InvalidMethodNameError;
use ArgumentCountError;
use TypeError;
use Exception;

/**
 * ** AbstractDataContainer **
 *
 * A container for working with data.
 *
 * Data is added to, and retrieved from an instance via `set` and `get methods.
 *
 * The model can populated from an array using the static::load static method.
 */
abstract class AbstractDataContainer
{
    /** @var array */
    protected $data = [];

    /**
     * Constructs the object.
     *
     * Optionally takes a array of data, $data, and InvoiceValidator instance with which to populate
     * the object.
     *
     * If $data is provided an InvoiceValidator instance must also be provided.
     *
     * @param array|null $data
     * @param InvoiceValidator|null $validator
     * @throws ValidationException
     * @throws ArgumentCountError
     */
    private function __construct(?array $data = null, ?InvoiceValidator $validator = null)
    {
        if ($data === null) {
            $this->setData(static::getBaseData());
        } else {
            if ($validator === null) {
                throw new ArgumentCountError(
                    'You must provide a InvoiceValidator instance when setting the data argument'
                );
            }
            if ($validator->validateArray($data, static::getSchemaDefinition())) {
                $this->setData($data);
            } else {
                throw new ValidationException($validator->getErrors());
            }
        }
    }

    /**
     * Returns a property name/property type map
     *
     * @return array
     */
    abstract public static function getDataKeys(): array;

    /**
     * Defines the named definition within the JSON schema against which to validate data.
     *
     * When NULL data is validated against the root schema.
     *
     * @return string|null
     */
    abstract protected static function getSchemaDefinition(): ?string;

    /**
     * Defines the base array structure for a new object instance
     *
     * @return array
     */
    abstract protected static function getBaseData(): array;

    /**
     * Returns an array structure containing complete invoice data.
     *
     * The array structure conforms to the JSON schema used by Serato\InvoiceQueue\InvoiceValidator.
     *
     * @return array
     */
    final public function getData(): array
    {
        return $this->data;
    }

    /**
     * Creates an instance
     *
     * @return static
     */
    final public static function create()
    {
        return new static();
    }

    /**
     * Creates an instance from an array.
     *
     * @param array $data
     * @param InvoiceValidator $validator
     * @return static
     */
    final public static function load(array $data, InvoiceValidator $validator)
    {
        return new static($data, $validator);
    }

    /**
     * Sets the underlying data array
     *
     * @param array $data
     * @return void
     */
    protected function setData(array $data): void
    {
        $this->data = $data;
    }

    /**
     * @throws InvalidMethodNameError
     * @throws ArgumentCountError
     * @throws TypeError
     */
    public function __call(string $methodName, array $args)
    {
        if (strpos($methodName, 'get') === 0) {
            $dataPropName = $this->getDataPropertyName(ltrim($methodName, 'get'), 'get');
            if (count($args) > 0) {
                throw new ArgumentCountError(
                    '`' . __CLASS__ . '::' . $methodName . '` expects 0 arguments. ' . count($args) . ' found.'
                );
            };
            return $this->getDataProp($dataPropName);
        }
        if (strpos($methodName, 'set') === 0) {
            $dataPropName = $this->getDataPropertyName(ltrim($methodName, 'set'), 'set');
            if (count($args) !== 1) {
                throw new ArgumentCountError(
                    '`' . __CLASS__ . '::' . $methodName . '` expects 1 argument. ' . count($args) . ' found.'
                );
            }

            $dataType = static::getDataKeys()[$dataPropName];

            if (gettype($args[0]) !== $dataType) {
                throw new TypeError(
                    'Invalid type for `' . __CLASS__ . '::' . $methodName . '`, argument 0. Expects ' . $dataType .
                    ', ' . gettype($args[0]) . ' found.'
                );
            }

            return $this->setDataProp($dataPropName, $args[0]);
        }
        throw new InvalidMethodNameError(
            'Invalid method name `' . __CLASS__ . '::' . $methodName . '`'
        );
    }

    /**
     * Gets a data property from a magic `get` method
     *
     * @param string $dataPropName
     * @return mixed
     */
    protected function getDataProp(string $dataPropName)
    {
        return isset($this->data[$dataPropName]) ? $this->data[$dataPropName] : null;
    }

    /**
     * Sets a data property from a magic `set` method
     *
     * @param string $dataPropName
     * @param mixed $val
     * @return self
     */
    protected function setDataProp(string $dataPropName, $val)
    {
        $this->data[$dataPropName] = $val;
        return $this;
    }

    /**
     * Maps a camel cased get or set method name to an internal snake cased data array key
     *
     * @param string $methodName
     * @return string
     *
     * @throws InvalidMethodNameError
     */
    private function getDataPropertyName(string $methodName, string $methodPrefix): string
    {
        $dataPropertyName = preg_replace_callback(
            '|([A-Z0-9])|',
            function ($matches) {
                return '_' . strtolower($matches[0]);
            },
            $methodName
        );
        if ($dataPropertyName === null) {
            # This should never happen :-)
            throw new Exception;
        }
        $dataPropertyName = ltrim($dataPropertyName, '_');
        if (!isset(static::getDataKeys()[$dataPropertyName])) {
            throw new InvalidMethodNameError(
                'Invalid method name `' . __CLASS__ . '::' . $methodPrefix . $methodName . '`.'
            );
        }
        return $dataPropertyName;
    }
}
