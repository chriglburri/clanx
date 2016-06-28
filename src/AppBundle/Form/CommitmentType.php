<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommitmentType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
        ->add('department', EntityType::class, array(
            'class'=>'AppBundle:Department',
            'label' => 'Für Ressort (ohne Garantie)',
            'choices' => $options['departmentChoices'],
            'choice_label' => function ($dpt) {
                                if ($dpt->getRequirement())
                                    return $dpt->getName().' ('.$dpt->getRequirement().')';
                                else
                                    return $dpt->getName();
                                }
        ))
        ->add('possibleStart', TextareaType::class, array(
            'label' => 'Ich helfe an folgenden Tagen (bitte auch Zeit angeben)',
            'attr' => array(
                'rows' => 4
            )
        ))
        ->add('shirtSize', ShirtSizeType::class, array(
            'label' => 'TShirt Grösse',
        ))
        ->add('needTrainTicket', CheckboxType::class, array(
            'label' => 'Ich brauche ein Zugbillet',
            'required' => false,
        ))
        ->add('remark', TextareaType::class, array(
            'label' => "Bemerkung / Wunsch",
            'required' => false
        ))
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'AppBundle\Entity\Commitment',
            'departmentChoices' => array()
        ));

        $resolver->isRequired('departmentChoices');
    }
}
