<?php

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shift
 *
 * @ORM\Table(name="shift", indexes={@ORM\Index(name="department_key", columns={"department_id"})})
 * @ORM\Entity
 */
class Shift
{
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="start", type="datetime", nullable=false)
     */
    private $start;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="end", type="datetime", nullable=true)
     */
    private $end;

    /**
     * @var integer
     *
     * @ORM\Column(name="mandatory_size", type="integer", nullable=false)
     */
    private $mandatorySize;

    /**
     * @var integer
     *
     * @ORM\Column(name="maximum_size", type="integer", nullable=false)
     */
    private $maximumSize;

    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="IDENTITY")
     */
    private $id;

    /**
     * @var \App\Entity\Department
     *
     * @ORM\ManyToOne(targetEntity="Department")
     * @ORM\JoinColumns({
     *   @ORM\JoinColumn(name="department_id", referencedColumnName="id")
     * })
     */
    private $department;


    /**
     * For validation
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context)
    {
        if($this->getEnd())
        {
            if ($this->getStart()->getTimestamp() >= $this->getEnd()->getTimestamp()) {
                $context->buildViolation('Das Ende muss nach dem Start sein')
                        ->atPath('end')
                        ->addViolation();
            }
        }
        if($this->getMandatorySize() > $this->getMaximumSize())
        {
            $context->buildViolation('Die maximale Grösse kann nicht kleiner als die erforderliche Grösse sein.')
                    ->atPath('maximumSize')
                    ->addViolation();
        }
    }

    /**
     * Set start
     *
     * @param \DateTime $start
     *
     * @return Shift
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * Get start
     *
     * @return \DateTime
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * Set end
     *
     * @param \DateTime $end
     *
     * @return Shift
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * Get end
     *
     * @return \DateTime
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * Set mandatorySize
     *
     * @param integer $mandatorySize
     *
     * @return Shift
     */
    public function setMandatorySize($mandatorySize)
    {
        $this->mandatorySize = $mandatorySize;

        return $this;
    }

    /**
     * Get mandatorySize
     *
     * @return integer
     */
    public function getMandatorySize()
    {
        return $this->mandatorySize;
    }

    /**
     * Set maximumSize
     *
     * @param integer $maximumSize
     *
     * @return Shift
     */
    public function setMaximumSize($maximumSize)
    {
        $this->maximumSize = $maximumSize;

        return $this;
    }

    /**
     * Get maximumSize
     *
     * @return integer
     */
    public function getMaximumSize()
    {
        return $this->maximumSize;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set department
     *
     * @var \App\Entity\Department $department
     *
     * @return Shift
     */
    public function setDepartment(\App\Entity\Department $department = null)
    {
        $this->department = $department;

        return $this;
    }

    /**
     * Get department
     *
     * @return \App\Entity\Department
     */
    public function getDepartment()
    {
        return $this->department;
    }

    public function __toString()
    {
        if($this->getStart())
        {
            $day = $this->getStart()->format('l');
            $hour = $this->getStart()->format('H');
            return $day.' '.$hour;
        }
        return '-';
    }

    public function getTimeDiff()
    {
        if($this->getStart() && $this->getEnd())
        {
            $interval = $this->getStart()->diff($this->getEnd());
            return $interval->format('%h:%I');
        }
        return '';
    }
}
