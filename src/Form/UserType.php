<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'form.username',
                'disabled' => !$options['new'],
                'help' => 'form.username.help',
            ])
            ->add('displayName', TextType::class, [
                'label' => 'form.displayName',
                'mapped' => false,
            ])
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'mapped' => false,
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'form.password.match',
                'options' => ['attr' => ['class' => 'password-field', 'placeholder' => $options['new'] ? '' : 'form.password.empty']],
                'required' => $options['new'],
                'first_options' => ['label' => 'form.password'],
                'second_options' => ['label' => 'form.password.repeat'],
            ])
            ->add('isAdmin', CheckboxType::class, [
                'label' => 'form.admin',
                'help' => 'form.admin.help',
                'required' => false,
                'mapped' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'save',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'new' => false,
            'data_class' => User::class,
        ]);
    }
}
