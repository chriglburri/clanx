<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MailType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('subject', null, array(
                'label' => 'Betreff',
                'mapped' => false,
            ))
            ->add('text',TextareaType::class,array(
                'attr' => array(
                    'rows' => 12,
                    'cols' => 80
                ),
                'mapped' => false,
            ))
        ;
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        //$resolver->setDefaults(array(
        //    'data_class' => Event::class
        //));
    }
}
