<?php

namespace App\Controller;

use App\Repository\CommentRepository;
use App\Repository\ItemRepository;
use App\Repository\UserRepository;
use App\Repository\VideoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;

class HomeController extends AbstractController
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'app_home')]
    public function index(ItemRepository $itemRepository, VideoRepository $videoRepository, CommentRepository $commentRepository, UserRepository $userRepository): Response
    {
        $items = $itemRepository->findAll();
        $videos = $videoRepository->findAll();
        $comments = $commentRepository->findAll();
        $users = $userRepository->findAll();

        return $this->render('home/index.html.twig', [
            'items' => $items,
            'videos' => $videos,
            'comments' => $comments,
            'users' => $users,
        ]);
    }

    #[Route('/search/users', name: 'app_home_search_users', methods: ['GET'])]
    public function searchUsers(Request $request, UserRepository $userRepository): Response
    {
        $query = $request->query->get('q', '');
        $this->logger->info('Search query (users): ' . $query);

        $users = $userRepository->createQueryBuilder('u')
            ->where('u.email LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('home/_user_list.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/search/videos', name: 'app_home_search_videos', methods: ['GET'])]
    public function searchVideos(Request $request, VideoRepository $videoRepository): Response
    {
        $query = $request->query->get('q', '');
        $this->logger->info('Search query (videos): ' . $query);

        $videos = $videoRepository->createQueryBuilder('v')
            ->where('v.title LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('home/_video_list.html.twig', [
            'videos' => $videos,
        ]);
    }
}
