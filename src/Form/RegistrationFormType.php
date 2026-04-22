<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'form.email',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'email',
                    'maxlength' => 180,
                    'placeholder' => 'form.email_placeholder',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'validators.email_required'),
                    new Assert\Email(message: 'validators.email_invalid'),
                    new Assert\Length(max: 180),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'form.username',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'username',
                    'maxlength' => 60,
                    'minlength' => 3,
                    'pattern' => '^[A-Za-z0-9_-]+$',
                    'placeholder' => 'form.username_placeholder',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'form.password',
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'minlength' => 8,
                    'maxlength' => 255,
                    'placeholder' => 'form.password_placeholder',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'validators.password_required'),
                    new Assert\Length(
                        min: 8,
                        max: 255,
                        minMessage: 'validators.password_min',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
