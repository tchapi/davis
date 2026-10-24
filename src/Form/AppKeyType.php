<?php

namespace App\Form;

use App\Entity\AppKey;
use App\Repository\AppKeyRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class AppKeyType extends AbstractType
{
    public function __construct(
        private AppKeyRepository $appKeys,
    ) {
    }

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
                'constraints' => [
                    new Assert\Callback(function (?string $value, ExecutionContextInterface $context) {
                        $scope = AppKey::parseScope((string) $value);

                        // A text with only commas or line breaks is not blank, but it has no username
                        if (!$scope) {
                            $context->buildViolation('app_key.scope.empty')->addViolation();

                            return;
                        }

                        if ($unknown = $this->appKeys->findUnknownUsers($scope)) {
                            $context->buildViolation('app_key.scope.unknown')
                                ->setParameter('{{ users }}', implode(', ', $unknown))
                                ->addViolation();
                        }
                    }),
                ],
                'attr' => ['rows' => 4, 'placeholder' => "alice\nbob"],
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
