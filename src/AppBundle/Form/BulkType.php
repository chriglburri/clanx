<?php

namespace AppBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use AppBundle\Form\BulkEntryType;
use AppBundle\Entity\Bulk;
use AppBundle\Entity\BulkEntry;


class BulkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('bulkEntries', CollectionType::class, array(
            'entry_type' => BulkEntryType::class
        ));

        $builder->add('bulkAction', ChoiceType::class, array(
            'choices' => $options['choices']
        ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Bulk::class,
            // you should pass choices in the options array, when you call
            // $this->createForm from your controller.
            'choices' => array('fd2b5168-f810-446c-af95-d3497ef75dd3'=>'fd2b5168-f810-446c-af95-d3497ef75dd3')
        ));
    }
}
