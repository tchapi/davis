<?php

namespace App\Form;

use App\Entity\AddressBook;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressBookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('principalUri', HiddenType::class, [
                'required' => true,
            ])
            ->add('uri', TextType::class, [
                'label' => 'form.uri',
                'disabled' => !$options['new'],
                'help' => 'form.uri.help.carddav',
            ])
            ->add('displayName', TextType::class, [
                'label' => 'form.displayName',
                'help' => 'form.name.help.carddav',
            ])
            ->add('description', TextAreaType::class, [
                'label' => 'form.description',
                'required' => false,
            ])
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
