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
                'label' => 'Email',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'email',
                    'maxlength' => 180,
                    'placeholder' => 'you@example.com',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Enter your email address.'),
                    new Assert\Email(message: 'Use a valid email address.'),
                    new Assert\Length(max: 180),
                ],
            ])
            ->add('username', TextType::class, [
                'label' => 'Username',
                'required' => true,
                'attr' => [
                    'autocomplete' => 'username',
                    'maxlength' => 60,
                    'minlength' => 3,
                    'pattern' => '^[A-Za-z0-9_-]+$',
                    'placeholder' => 'your-name',
                ],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Password',
                'mapped' => false,
                'required' => true,
                'attr' => [
                    'autocomplete' => 'new-password',
                    'minlength' => 8,
                    'maxlength' => 255,
                    'placeholder' => 'At least 8 characters',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Choose a password.'),
                    new Assert\Length(
                        min: 8,
                        max: 255,
                        minMessage: 'Your password should be at least {{ limit }} characters.',
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
