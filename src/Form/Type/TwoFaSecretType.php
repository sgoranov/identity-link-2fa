<?php
declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TwoFaSecretType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $constraints = [
            'code' => new NotBlank(),
        ];

        $resolver->setDefaults([
            'data_class' => null,
            'constraints' => $constraints,
            'csrf_protection' => true,
        ]);
    }
}