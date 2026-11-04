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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
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
                'constraints' => [
                    new Assert\Length(max: 255),
                ],
            ])
            // The field is unmapped — it belongs to the Principal, not the User — so the entity's
            // own constraints never run on it and the rules have to live here. An empty address
            // keeps the principal out of its own `calendar-user-address-set`, which means sabre
            // never emits a scheduling message and invitations are silently never sent.
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'mapped' => false,
                'help' => 'form.email.help',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Email(),
                    new Assert\Length(max: 255),
                ],
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'new' => false,
            'data_class' => User::class,
            // The username rule only applies to new accounts: the field is disabled when editing,
            // and an account created before the rule (or by LDAP/IMAP) must stay editable.
            'validation_groups' => static fn (FormInterface $form): array => $form->getConfig()->getOption('new')
                ? ['Default', 'creation']
                : ['Default'],
        ]);
    }
}
