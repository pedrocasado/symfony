<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Context\LegacyExecutionContext;
use Symfony\Component\Validator\ExecutionContextInterface as LegacyExecutionContextInterface;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Mapping\PropertyMetadata;
use Symfony\Component\Validator\Tests\Fixtures\StubGlobalExecutionContext;
use Symfony\Component\Validator\Validation;

/**
 * @since 2.5.3
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractConstraintValidatorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ExecutionContextInterface
     */
    protected $context;

    /**
     * @var ConstraintValidatorInterface
     */
    protected $validator;

    protected $group;
    protected $metadata;
    protected $object;
    protected $value;
    protected $root;
    protected $propertyPath;
    protected $constraint;
    protected $defaultTimezone;

    protected function setUp()
    {
        if (Validation::API_VERSION_2_5 !== $this->getApiVersion()) {
            $this->iniSet('error_reporting', -1 & ~E_USER_DEPRECATED);
        }

        $this->group = 'MyGroup';
        $this->metadata = null;
        $this->object = null;
        $this->value = 'InvalidValue';
        $this->root = 'root';
        $this->propertyPath = 'property.path';

        // Initialize the context with some constraint so that we can
        // successfully build a violation.
        $this->constraint = new NotNull();

        $this->context = $this->createContext();
        $this->validator = $this->createValidator();
        $this->validator->initialize($this->context);

        \Locale::setDefault('en');

        $this->setDefaultTimezone('UTC');
    }

    protected function tearDown()
    {
        $this->restoreDefaultTimezone();
    }

    protected function setDefaultTimezone($defaultTimezone)
    {
        // Make sure this method can not be called twice before calling
        // also restoreDefaultTimezone()
        if (null === $this->defaultTimezone) {
            $this->defaultTimezone = date_default_timezone_get();
            date_default_timezone_set($defaultTimezone);
        }
    }

    protected function restoreDefaultTimezone()
    {
        if (null !== $this->defaultTimezone) {
            date_default_timezone_set($this->defaultTimezone);
            $this->defaultTimezone = null;
        }
    }

    protected function createContext()
    {
        $translator = $this->getMock('Symfony\Component\Translation\TranslatorInterface');
        $validator = $this->getMock('Symfony\Component\Validator\Validator\ValidatorInterface');
        $contextualValidator = $this->getMock('Symfony\Component\Validator\Validator\ContextualValidatorInterface');

        switch ($this->getApiVersion()) {
            case Validation::API_VERSION_2_5:
                $context = new ExecutionContext(
                    $validator,
                    $this->root,
                    $translator
                );
                break;
            case Validation::API_VERSION_2_5_BC:
                $context = new LegacyExecutionContext(
                    $validator,
                    $this->root,
                    $this->getMock('Symfony\Component\Validator\MetadataFactoryInterface'),
                    $translator
                );
                break;
            default:
                throw new \RuntimeException('Invalid API version');
        }

        $context->setGroup($this->group);
        $context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
        $context->setConstraint($this->constraint);

        $validator->expects($this->any())
            ->method('inContext')
            ->with($context)
            ->will($this->returnValue($contextualValidator));

        return $context;
    }

    /**
     * @param mixed  $message
     * @param array  $parameters
     * @param string $propertyPath
     * @param string $invalidValue
     * @param null   $plural
     * @param null   $code
     *
     * @return ConstraintViolation
     *
     * @deprecated to be removed in Symfony 3.0. Use {@link buildViolation()} instead.
     */
    protected function createViolation($message, array $parameters = array(), $propertyPath = 'property.path', $invalidValue = 'InvalidValue', $plural = null, $code = null)
    {
        return new ConstraintViolation(
            null,
            $message,
            $parameters,
            $this->root,
            $propertyPath,
            $invalidValue,
            $plural,
            $code,
            $this->constraint
        );
    }

    protected function setGroup($group)
    {
        $this->group = $group;
        $this->context->setGroup($group);
    }

    protected function setObject($object)
    {
        $this->object = $object;
        $this->metadata = is_object($object)
            ? new ClassMetadata(get_class($object))
            : null;

        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setProperty($object, $property)
    {
        $this->object = $object;
        $this->metadata = is_object($object)
            ? new PropertyMetadata(get_class($object), $property)
            : null;

        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setValue($value)
    {
        $this->value = $value;
        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function setRoot($root)
    {
        $this->root = $root;
        $this->context = $this->createContext();
        $this->validator->initialize($this->context);
    }

    protected function setPropertyPath($propertyPath)
    {
        $this->propertyPath = $propertyPath;
        $this->context->setNode($this->value, $this->object, $this->metadata, $this->propertyPath);
    }

    protected function expectNoValidate()
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expects($this->never())
            ->method('atPath');
        $validator->expects($this->never())
            ->method('validate');
    }

    protected function expectValidateAt($i, $propertyPath, $value, $group)
    {
        $validator = $this->context->getValidator()->inContext($this->context);
        $validator->expects($this->at(2 * $i))
            ->method('atPath')
            ->with($propertyPath)
            ->will($this->returnValue($validator));
        $validator->expects($this->at(2 * $i + 1))
            ->method('validate')
            ->with($value, $this->logicalOr(null, array()), $group);
    }

    protected function expectValidateValueAt($i, $propertyPath, $value, $constraints, $group = null)
    {
        $contextualValidator = $this->context->getValidator()->inContext($this->context);
        $contextualValidator->expects($this->at(2 * $i))
            ->method('atPath')
            ->with($propertyPath)
            ->will($this->returnValue($contextualValidator));
        $contextualValidator->expects($this->at(2 * $i + 1))
            ->method('validate')
            ->with($value, $constraints, $group);
    }

    protected function assertNoViolation()
    {
        $this->assertCount(0, $this->context->getViolations());
    }

    /**
     * @param mixed  $message
     * @param array  $parameters
     * @param string $propertyPath
     * @param string $invalidValue
     * @param null   $plural
     * @param null   $code
     *
     * @deprecated To be removed in Symfony 3.0. Use
     *             {@link buildViolation()} instead.
     */
    protected function assertViolation($message, array $parameters = array(), $propertyPath = 'property.path', $invalidValue = 'InvalidValue', $plural = null, $code = null)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0. Use the buildViolation() method instead.', E_USER_DEPRECATED);

        $this->buildViolation($message)
            ->setParameters($parameters)
            ->atPath($propertyPath)
            ->setInvalidValue($invalidValue)
            ->setCode($code)
            ->setPlural($plural)
            ->assertRaised();
    }

    /**
     * @param array $expected
     *
     * @deprecated To be removed in Symfony 3.0. Use
     *             {@link buildViolation()} instead.
     */
    protected function assertViolations(array $expected)
    {
        trigger_error('The '.__METHOD__.' method is deprecated since version 2.3 and will be removed in 3.0. Use the buildViolation() method instead.', E_USER_DEPRECATED);

        $violations = $this->context->getViolations();

        $this->assertCount(count($expected), $violations);

        $i = 0;

        foreach ($expected as $violation) {
            $this->assertEquals($violation, $violations[$i++]);
        }
    }

    /**
     * @param $message
     *
     * @return ConstraintViolationAssertion
     */
    protected function buildViolation($message)
    {
        return new ConstraintViolationAssertion($this->context, $message, $this->constraint);
    }

    abstract protected function getApiVersion();

    abstract protected function createValidator();
}

/**
 * @internal
 */
class ConstraintViolationAssertion
{
    /**
     * @var LegacyExecutionContextInterface
     */
    private $context;

    /**
     * @var ConstraintViolationAssertion[]
     */
    private $assertions;

    private $message;
    private $parameters = array();
    private $invalidValue = 'InvalidValue';
    private $propertyPath = 'property.path';
    private $translationDomain;
    private $plural;
    private $code;
    private $constraint;
    private $cause;

    public function __construct(LegacyExecutionContextInterface $context, $message, Constraint $constraint = null, array $assertions = array())
    {
        $this->context = $context;
        $this->message = $message;
        $this->constraint = $constraint;
        $this->assertions = $assertions;
    }

    public function atPath($path)
    {
        $this->propertyPath = $path;

        return $this;
    }

    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;

        return $this;
    }

    public function setParameters(array $parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    public function setTranslationDomain($translationDomain)
    {
        $this->translationDomain = $translationDomain;

        return $this;
    }

    public function setInvalidValue($invalidValue)
    {
        $this->invalidValue = $invalidValue;

        return $this;
    }

    public function setPlural($number)
    {
        $this->plural = $number;

        return $this;
    }

    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    public function setCause($cause)
    {
        $this->cause = $cause;

        return $this;
    }

    public function buildNextViolation($message)
    {
        $assertions = $this->assertions;
        $assertions[] = $this;

        return new self($this->context, $message, $this->constraint, $assertions);
    }

    public function assertRaised()
    {
        $expected = array();
        foreach ($this->assertions as $assertion) {
            $expected[] = $assertion->getViolation();
        }
        $expected[] = $this->getViolation();

        $violations = iterator_to_array($this->context->getViolations());

        \PHPUnit_Framework_Assert::assertCount(count($expected), $violations);

        reset($violations);

        foreach ($expected as $violation) {
            \PHPUnit_Framework_Assert::assertEquals($violation, current($violations));
            next($violations);
        }
    }

    private function getViolation()
    {
        return new ConstraintViolation(
            null,
            $this->message,
            $this->parameters,
            $this->context->getRoot(),
            $this->propertyPath,
            $this->invalidValue,
            $this->plural,
            $this->code,
            $this->constraint,
            $this->cause
        );
    }
}
