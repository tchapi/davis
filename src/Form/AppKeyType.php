<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

class AppKeyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'form.app_key.name',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('scope', TextareaType::class, [
                'label' => 'form.app_key.scope',
                'help' => 'form.app_key.scope.help',
                'required' => false,
                'empty_data' => '*',
                'attr' => ['rows' => 4, 'placeholder' => '*'],
            ])
            ->add('expiresAt', DateType::class, [
                'label' => 'form.app_key.expires_at',
                'help' => 'form.app_key.expires_at.help',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'save',
            ]);
    }
}
