<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Entity\Companion;

class CompanionType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name',null,array(
                'label' => 'Name',
            ))
            ->add('email',null,array(
                'label' => 'Emailadresse',
            ))
            ->add('phone',null,array(
                'label' => 'Telefonnummer',
            ))
            ->add('isRegular', CheckboxType::class, array(
                'label' => 'Ist Stammhölfer?',
                'required' => false,
            ))
            ->add('remark', TextareaType::class, array(
                'label' => "Bemerkung",
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
            'data_class' => Companion::class
        ));
    }
}
