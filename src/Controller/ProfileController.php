<?php
// src/Controller/ProfileController.php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CommentRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile/{id}', name: 'app_user_profile')]
    public function show(User $user, Security $security, VideoRepository $videoRepository, CommentRepository $commentRepository): Response
    {
        $currentUser = $security->getUser();
        $canEdit = $currentUser && $currentUser->getId() === $user->getId();

        // Récupérer les vidéos et les commentaires postés par l'utilisateur
        $videos = $videoRepository->findBy(['user' => $user]);
        $comments = $commentRepository->findBy(['user' => $user]);

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'canEdit' => $canEdit,
            'videos' => $videos,
            'comments' => $comments,
        ]);
    }
}
