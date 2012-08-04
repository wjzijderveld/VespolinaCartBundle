<?php

namespace Vespolina\CartBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;


class CartItem extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('product', new Product());
        $builder->add('quantity', 'integer', array('required' => true ));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => 'Vespolina\Entity\Order\Item',
            'cascade_validation' => true,
            ));
    }

    public function getName()
    {
        return 'items';
    }

}
