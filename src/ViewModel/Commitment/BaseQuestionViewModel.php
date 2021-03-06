<?php
namespace App\ViewModel\Commitment;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use App\Entity\Question;
use App\Entity\Answer;

/**
 * Base class for all questions.
 * Questions are mapped to their domain model in the class QuestionService.
 */
abstract class BaseQuestionViewModel
{
    /**
     * @var integer
     */
    private $id;

    /**
     * The text of the question.
     * @var string
     */
    private $text;

    /**
     * The description of the question.
     * This is basically just a bit more text if this is needed.
     * @var string
     */
    private $hint;

    /**
     * @var boolean
     */
    private $required;

    /**
     * @var boolean
     */
    private $aggregate;

    /**
     * @var array
     */
    private $data;

    /**
     * @param Question $q
     * @param Anser $a
     */
    function __construct(Question $q, Answer $a=null)
    {
        $this->setId($q->getId());
        $this->setText($q->getText());
        $this->setHint($q->getHint());
        $this->setRequired(!$q->getOptional());
        $this->setAggregate($q->getAggregate());

        try {
            $this->setData(json_decode($q->getData(), true));
        } catch (Exception $e) {
            $this->setData(array());
        }
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param integer $id
     * @return BaseQuestionViewModel
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this; // for setter chains
    }

    /**
     * Gets the text.
     * @return string
     */
    public function getText(){return $this->text;}

    /**
     * Sets the text.
     * @param string $value
     * @return BaseQuestionViewModel
     */
    public function setText($value='')
    {
        $this->text = $value;
        return $this; // for setter chains
    }

    /**
     * Gets the hint
     * @return string
     */
    public function getHint(){return $this->hint;}

    /**
     * Sets the hint
     * @param string $value
     * @return BaseQuestionViewModel
     */
    public function setHint($value='')
    {
        $this->hint = $value;
        return $this; // for setter chains
    }

    /**
     * @return boolean
     */
    public function getRequired(){return $this->required;}

    /**
     * @param boolean $value
     * @return BaseQuestionViewModel
     */
    public function setRequired($value)
    {
        $this->required = $value;
        return $this; // for setter chains
    }

    /**
     * @return boolean
     */
    public function getAggregate(){return $this->aggregate;}

    /**
     * @param boolean $value
     * @return BaseQuestionViewModel
     */
    public function setAggregate($value)
    {
        $this->aggregate = $value;
        return $this; // for setter chains
    }

    /**
     * @return array
     */
    public function getData(){return $this->data;}

    /**
     * @param array $value
     * @return BaseQuestionViewModel
     */
    public function setData($value)
    {
        $this->data = $value;
        return $this; // for setter chains
    }

    /**
     * Returns the json encoded data
     * @return string
     */
    public function getJsonData()
    {
        return json_encode($this->getData());
    }

    /**
     * @param  string $prefix
     * @param  string $postfix
     * @return string
     */
    public function getFormFieldName($prefix='',$postfix='')
    {
        return $prefix . 'answer' . $this->id . $postfix;
    }

    /**
     * Gets the predefined answer, depanding if it defined.
     * If no default is defined, the method returns false.
     * @return mixed
     */
    public function getDefaultAnswer()
    {
        $arr = $this->getData();
        if(isset($arr["default"]))
        {
            return $arr["default"];
        }
        return $this->getUndefiniedDefaultAnswer();
    }

    public function hasDefault()
    {
        $arr = $this->getData();
        return isset($arr["default"]);
    }

    public function getPropertyPath()
    {
        return 'questions[' . $this->getId() . '].answer';
    }

    /**
     * Gets the string that identifies this question type in the database.
     * @return [type] [description]
     */
    public abstract function getTypeString();

    /**
     * @return object This may be a bool, string, array, you name it.
     */
    abstract public function getAnswer();

    /**
     * @param object $value This may be a bool, string, array, you name it.
     */
    abstract public function setAnswer($value);

    /**
     *
     * @return string fully qualified class name.
     */
    abstract public function getFormType();

    /**
     * @param  array $attributes The attributes array for the question field.
     * This is an out parameter, it will be changed (filled) by this method
     */
    abstract public function fillAttributes($attributes);

    /**
     * Gets an array of selection possibilities
     * @return array
     */
    abstract public function getSelection();

    /**
     * returns the default answer that is used when 'default' is not defined
     * in the data field.
     * @return mixed
     */
    protected abstract function getUndefiniedDefaultAnswer();

    /**
     * Validates the answer
     * @param  ExecutionContextInterface $context
     */
    public abstract function validateAnswer(ExecutionContextInterface $context);
}
