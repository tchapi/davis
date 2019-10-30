<?php

namespace App\Form;

use App\Entity\AddressBook;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressBookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('uri', TextType::class, ['disabled' => !$options['new'], 'help' => "This is the unique identifier for this address book. Allowed characters are digits, lowercase letters and the dash symbol '-'."])
            ->add('displayName', TextType::class, ['help' => 'This name will be displayed in your CardDAV client'])
            ->add('description')
            ->add('save', SubmitType::class, [
                'label' => 'save',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'new' => false,
            'data_class' => AddressBook::class,
        ]);
    }
}
