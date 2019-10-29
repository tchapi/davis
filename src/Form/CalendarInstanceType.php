<?php

namespace App\Form;

use App\Entity\CalendarInstance;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CalendarInstanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('uri', TextType::class, ['disabled' => !$options['new'], 'help' => "THis is the unique identifier for this calendar. Allowed characters are digits, lowercase letters and the dash symbol '-'.", 'required' => true])
            ->add('displayName', TextType::class, ['help' => 'This name will be displayed in your CalDAV client'])
            ->add('description')
            ->add('calendarColor', TextType::class, ['required' => false, 'help' => "This is the color that will be displayed in your CalDAV client. It must be supplied in the format '#RRGGBBAA' (alpha channel is optional) with hexadecimal values. This value is optional."])
            ->add('todos', CheckboxType::class, [
                'mapped' => false,
                'help' => 'If checked, todos will be enabled on this calendar.',
            ])
            ->add('notes', CheckboxType::class, [
                'mapped' => false,
                'help' => 'If checked, notes will be enabled on this calendar.',
            ])
            ->add('save', SubmitType::class);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'new' => false,
            'data_class' => CalendarInstance::class,
        ]);
    }
}
