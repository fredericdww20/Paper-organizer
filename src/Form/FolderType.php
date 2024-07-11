<?php

namespace App\Form;

use App\Entity\Folder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;

class FolderType extends AbstractType
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];

        $builder
            ->add('name', TextType::class)
            ->add('parent', EntityType::class, [
                'class' => Folder::class,
                'query_builder' => function ($repository) use ($user) {
                    return $repository->createQueryBuilder('f')
                        ->where('f.owner = :owner')
                        ->setParameter('owner', $user);
                },
                'choice_label' => 'name',
                'required' => false,
                'placeholder' => 'Root Folder'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Folder::class,
            'user' => null,
        ]);
    }
}
