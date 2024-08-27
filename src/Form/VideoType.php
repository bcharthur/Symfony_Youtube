<?php
// src/Form/VideoType.php

namespace App\Form;

use App\Entity\Categorie;
use App\Entity\Video;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class VideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('title')
            ->add('categorie', EntityType::class, [
                'class' => Categorie::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a category',
                'required' => true,
                'label' => 'Category',
                'attr' => ['class' => 'form-control']
            ]);

        if (!$isEdit) {
            $builder
                ->add('videoFile', FileType::class, [
                    'label' => 'Video File (MP4 file)',
                    'mapped' => false,
                    'required' => true,
                    'constraints' => [
                        new File([
                            'maxSize' => '102400000k',
                            'mimeTypes' => [
                                'video/mp4',
                            ],
                            'mimeTypesMessage' => 'Please upload a valid MP4 file',
                        ])
                    ],
                ])
                ->add('thumbnailFile', FileType::class, [
                    'label' => 'Thumbnail File',
                    'mapped' => false,
                    'required' => false,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Video::class,
            'is_edit' => false,
        ]);
    }
}
