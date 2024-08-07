<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NewGameType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('mode', ChoiceType::class, [
                'choices' => [
                    'Seul joueur' => 'player',
                    'Multijoueur' => 'multiplayer',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'player',
            ])
            ->add('difficulty', ChoiceType::class, [
                'choices' => [
                    'Normal' => 'normal',
                    'Difficile' => 'hard'
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'normal',
                'choice_attr' => function ($choice, $key, $value) {
                return ['class' => 'm-2'];
                },
            ])
            ->add('codeLength', ChoiceType::class, [
                'choices' => [
                    'Aléatoire' => null,
                    '4' => 4,
                    '5' => 5,
                    '6' => 6,
                    '7' => 7,
                    '8' => 8,
                    '9' => 9,
                ],
            ])
            ->add('private', ChoiceType::class, [
                'choices' => [
                    'Public' => 'public',
                    'Privée' => 'private',
                ],
                'expanded' => true,
                'multiple' => false,
                'data' => 'public'
            ])
            ->add('joinGame', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Identifiant'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
