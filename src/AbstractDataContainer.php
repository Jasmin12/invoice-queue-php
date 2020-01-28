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
 * Data attributes are defined in a array returned by `AbstractDataContainer::getDataKeys` method.
 * This array takes the form of keys representing attribute names and values representing data types.
 * Only scalar types are supported - boolean, integer, float and string.
 *
 * Data attributes can then be accessed via magic `get` and `set` methods whose names are camel cased
 * versions of the data attribute names, prefixed with "get" and "set" accordingly.
 *
 * eg. for the following data attribute array:
 *
 *    [
 *         'first_name' => 'string',
 *         'net_tax_amount' => 'integer'
 *    ];
 *
 *    $instance->setFirstName('Bob');
 *    $instance->getFirstName();
 *    $instance->setNetTaxAmount(123);
 *    $instance->getNetTaxAmount();
 *
 * The underlying data can be accessed in it's entirety via the public `AbstractDataContainer::getData`
 * method.
 *
 * A model can be created and populated from an array using the public
 * `AbstractDataContainer::load` static method. The array should be of the same structure as that returned
 * from the `AbstractDataContainer::getData` and will be validated using a `Serato\InvoiceQueue\InvoiceValidator`
 * instance.
 */
abstract class AbstractDataContainer
{
    /** @var array */
    protected $data = [];

    /** @var InvoiceValidator */
    protected $validator;

    /**
     * Constructs the object.
     *
     * Optionally takes a array of data, $data, and InvoiceValidator instance with which to populate
     * the object.
     *
     * @param array|null $data
     * @param InvoiceValidator|null $validator
     * @throws ValidationException
     */
    private function __construct(?array $data = null, ?InvoiceValidator $validator = null)
    {
        if ($data === null) {
            $this->setData(static::getBaseData());
        } else {
            if ($validator === null) {
                $this->validator = new InvoiceValidator;
            } else {
                $this->validator = $validator;
            }
            if ($this->validator->validateArray($data, static::getSchemaDefinition())) {
                $this->setData($data);
            } else {
                throw new ValidationException($this->validator->getErrors());
            }
        }
    }

    /**
     * Returns a property name/type map. Array takes the form of keys representing attribute
     * names and values representing data types.
     *
     * eg.
     *    [
     *         'first_name' => 'string',
     *         'net_tax_amount' => 'integer'
     *    ];
     *
     * @return array
     */
    abstract protected static function getDataKeys(): array;

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
     * @param InvoiceValidator|null $validator
     * @return static
     */
    final public static function load(array $data, ?InvoiceValidator $validator)
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
     * @return mixed
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
