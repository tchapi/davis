<?php

namespace App\Form;

use App\Entity\CalendarInstance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarInstanceType extends AbstractType
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
                'help' => 'form.uri.help.caldav',
                'required' => true,
            ])
            ->add('displayName', TextType::class, [
                'label' => 'form.displayName',
                'help' => 'form.name.help.caldav',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'form.description',
                'required' => false,
            ])
            ->add('calendarColor', TextType::class, [
                'label' => 'form.color',
                'required' => false,
                'help' => 'form.color.help',
                'attr' => ['placeholder' => '#RRGGBBAA'],
            ])
            ->add('todos', CheckboxType::class, [
                'label' => 'form.todos',
                'mapped' => false,
                'help' => 'form.todos.help',
                'required' => false,
            ])
            ->add('notes', CheckboxType::class, [
                'label' => 'form.notes',
                'mapped' => false,
                'help' => 'form.notes.help',
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
            'data_class' => CalendarInstance::class,
        ]);
    }
}
