<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\User;
use App\Entity\Video;
use App\Form\CommentType;
use App\Form\VideoType;
use App\Repository\CategorieRepository;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/video')]
class VideoController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_video_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository, CategorieRepository $categorieRepository): Response
    {
        return $this->render('video/index.html.twig', [
            'videos' => $videoRepository->findAll(),
            'categories' => $categorieRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_video_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $video = new Video();
        $form = $this->createForm(VideoType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $video->setUser($this->getUser());
            $videoFile = $form->get('videoFile')->getData();
            $thumbnailFile = $form->get('thumbnailFile')->getData();

            if ($videoFile) {
                $newFilename = pathinfo($videoFile->getClientOriginalName(), PATHINFO_FILENAME) . '-' . uniqid() . '.' . $videoFile->guessExtension();
                $videoFile->move($this->getParameter('videos_directory'), $newFilename);
                $video->setFilename($newFilename);
            }

            if ($thumbnailFile) {
                $thumbnailFilename = uniqid() . '.' . $thumbnailFile->guessExtension();
                $thumbnailFile->move($this->getParameter('thumbnails_directory'), $thumbnailFilename);
                $video->setThumbnailFilename($thumbnailFilename);
            }

            $video->setCreatedAt(new \DateTime());
            $entityManager->persist($video);
            $entityManager->flush();

            return $this->redirectToRoute('app_video_index');
        }

        return $this->render('video/new.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_video_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Video $video, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(VideoType::class, $video, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Seul le titre est modifiable, donc pas besoin de toucher aux autres propriétés
            $entityManager->flush();

            return $this->redirectToRoute('app_video_show', ['id' => $video->getId()]);
        }

        return $this->render('video/edit.html.twig', [
            'video' => $video,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/video/{id}', name: 'app_video_delete', methods: ['POST'])]
    public function delete(Request $request, Video $video, EntityManagerInterface $entityManager): Response
    {
        $csrfToken = $request->request->get('_token');
        $this->logger->info('Attempting to delete video with ID: ' . $video->getId());

        if ($this->isCsrfTokenValid('delete' . $video->getId(), $csrfToken)) {
            // Suppression des commentaires associés
            foreach ($video->getComments() as $comment) {
                $entityManager->remove($comment);
            }

            // Suppression des likes associés
            foreach ($video->getLikes() as $like) {
                $entityManager->remove($like);
            }

            // Suppression de la vidéo
            $entityManager->remove($video);
            $entityManager->flush();

            $this->addFlash('success', 'La vidéo a été supprimée avec succès.');

            return $this->redirectToRoute('app_video_index');
        }

        return $this->redirectToRoute('app_video_show', ['id' => $video->getId()]);
    }

    #[Route('/{id}', name: 'app_video_show', methods: ['GET', 'POST'])]
    public function show(Video $video, VideoRepository $videoRepository, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Gestion des commentaires
        $comment = new Comment();
        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $comment->setUser($this->getUser());
            $comment->setVideo($video);
            $comment->setCreatedAt(new \DateTime());

            $entityManager->persist($comment);
            $entityManager->flush();

            if ($request->isXmlHttpRequest()) {
                $profilePictureUrl = $this->getUser()->getProfilePicture()
                    ? $this->getParameter('app') . '/uploads/profile_pictures/' . $this->getUser()->getProfilePicture()
                    : 'https://via.placeholder.com/150';

                return $this->json([
                    'userId' => $this->getUser()->getId(),
                    'username' => $this->getUser()->getUsername(),
                    'profilePicture' => $profilePictureUrl,
                    'content' => $comment->getContent(),
                    'createdAt' => $comment->getCreatedAt()->format('d/m/Y H:i'),
                ]);
            }

            return $this->redirectToRoute('app_video_show', ['id' => $video->getId()]);
        }

        $comments = $video->getComments()->toArray();
        usort($comments, fn($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        // Récupération des autres vidéos
        $offset = $request->query->getInt('offset', 0);
        $limit = 5;
        $otherVideos = $videoRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
        $totalVideos = $videoRepository->count([]);

        // Récupération des abonnements
        $abonnementRepo = $entityManager->getRepository(Abonnement::class);
        $isAbonne = $abonnementRepo->findOneBy(['abonne' => $this->getUser(), 'cible' => $video->getUser()]);
        $abonnementsCount = $abonnementRepo->count(['cible' => $video->getUser()]);

        return $this->render('video/show.html.twig', [
            'video' => $video,
            'other_videos' => $otherVideos,
            'offset' => $offset,
            'limit' => $limit,
            'total_videos' => $totalVideos,
            'comment_form' => $commentForm->createView(),
            'comments' => $comments,
            'abonnements' => $isAbonne ? [$isAbonne] : [],
            'abonnements_count' => $abonnementsCount,
        ]);
    }

    #[Route('/{id}/like', name: 'app_video_like', methods: ['POST'])]
    public function like(Video $video, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Vous devez être connecté pour liker une vidéo.'], 403);
        }

        $likeRepository = $entityManager->getRepository(Like::class);
        $existingLike = $likeRepository->findOneBy(['video' => $video, 'user' => $user]);
        $liked = false;

        if ($existingLike) {
            $entityManager->remove($existingLike);
        } else {
            $like = new Like();
            $like->setVideo($video);
            $like->setUser($user);
            $entityManager->persist($like);
            $liked = true;
        }

        $entityManager->flush();
        $likesCount = $likeRepository->count(['video' => $video]);

        return new JsonResponse(['likes_count' => $likesCount, 'liked' => $liked]);
    }

    #[Route('/{id}/load-more', name: 'app_video_load_more', methods: ['GET'])]
    public function loadMoreVideos(VideoRepository $videoRepository, Request $request, $id): JsonResponse
    {
        $offset = $request->query->getInt('offset', 0);
        $limit = $request->query->getInt('limit', 5);
        $otherVideos = $videoRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

        return $this->json([
            'videos' => array_map(fn($video) => [
                'id' => $video->getId(),
                'title' => $video->getTitle(),
                'createdAt' => $video->getCreatedAt()->format('d/m/Y H:i'),
                'thumbnail' => $video->getThumbnailFilename()
            ], $otherVideos)
        ]);
    }

    #[Route('/{id}/comment', name: 'app_video_comment', methods: ['POST'])]
    public function comment(Request $request, Video $video, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Vous devez être connecté pour commenter.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $content = $request->request->get('content');

        if (empty($content)) {
            return new JsonResponse(['error' => 'Le contenu du commentaire ne peut pas être vide.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $comment = new Comment();
        $comment->setUser($user);
        $comment->setVideo($video);
        $comment->setContent($content);
        $comment->setCreatedAt(new \DateTime());

        $entityManager->persist($comment);
        $entityManager->flush();

        $profilePictureUrl = $user->getProfilePicture()
            ? $this->getParameter('app') . '/uploads/profile_pictures/' . $user->getProfilePicture()
            : 'https://via.placeholder.com/150';

        return new JsonResponse([
            'userId' => $user->getId(),
            'username' => $user->getUsername(),
            'profilePicture' => $profilePictureUrl,
            'content' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format('d/m/Y H:i'),
        ]);
    }

    #[Route('/{id}/comments-count', name: 'app_video_comments_count', methods: ['GET'])]
    public function getCommentsCount(Video $video, EntityManagerInterface $entityManager): JsonResponse
    {
        $commentsCount = $entityManager->getRepository(Comment::class)->count(['video' => $video]);
        return new JsonResponse(['comments_count' => $commentsCount]);
    }

    #[Route('/{id}/subscribers-count', name: 'app_video_subscribers_count', methods: ['GET'])]
    public function getSubscribersCount(Video $video, EntityManagerInterface $entityManager): JsonResponse
    {
        $subscribersCount = $entityManager->getRepository(Abonnement::class)->count(['cible' => $video->getUser()]);
        return new JsonResponse(['subscribers_count' => $subscribersCount]);
    }
}
