<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\User;
use App\Entity\Video;
use App\Form\APIType;
use App\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use YoutubeDl\Exception\YoutubeDlException;
use YoutubeDl\YoutubeDl;
use YoutubeDl\Options;
// APIYoutubeController.php

#[Route('/api/youtube')]
class APIYoutubeController extends AbstractController
{
    private $params;
    private $slugger;

    public function __construct(ParameterBagInterface $params, SluggerInterface $slugger)
    {
        $this->params = $params;
        $this->slugger = $slugger;
    }

    #[Route('/', name: 'app_api_youtube_index', methods: ['GET'])]
    public function index(VideoRepository $videoRepository): Response
    {
        return $this->render('api_youtube/index.html.twig', [
            'videos' => $videoRepository->findAll(),
        ]);
    }

    #[Route('/download', name: 'app_api_youtube_download', methods: ['POST'])]
    public function downloadVideos(Request $request, EntityManagerInterface $entityManager, LoggerInterface $logger): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $links = isset($data['links']) ? $data['links'] : [$request->request->get('youtube_link')];

        if (empty($links)) {
            return new JsonResponse(['status' => 'error', 'message' => 'No links provided'], 400);
        }

        $logger->info('Début du téléchargement des vidéos', ['links' => $links]);

        $youtubeDl = new YoutubeDl();
        $youtubeDl->setBinPath('/usr/local/bin/youtube-dl'); // Assurez-vous que ce chemin est correct

        $downloadStatus = [];

        foreach ($links as $link) {
            try {
                $logger->info('Téléchargement de la vidéo', ['link' => $link]);

                $video = $youtubeDl->download(
                    Options::create()
                        ->downloadPath($this->params->get('videos_directory'))
                        ->url($link)
                        ->format('best')
                        ->output('%(id)s.%(ext)s')
                );

                foreach ($video->getVideos() as $v) {
                    if ($v->getError() !== null) {
                        throw new \Exception('Error downloading video: ' . $v->getError());
                    }

                    $videoId = $v->getId();
                    $title = $v->getTitle();
                    $filename = $videoId . '.mp4';
                    $thumbnailFilename = $videoId . '.jpg';

                    // Enregistrement de la miniature
                    if (!empty($v->getThumbnails())) {
                        $thumbnailUrl = $v->getThumbnails()[0]->getUrl();
                        $logger->info('Enregistrement de la miniature', ['thumbnailUrl' => $thumbnailUrl]);

                        file_put_contents($this->params->get('thumbnails_directory') . '/' . $thumbnailFilename, file_get_contents($thumbnailUrl));
                    } else {
                        $thumbnailFilename = null;
                    }

                    // Enregistrement en base de données
                    $videoEntity = new Video();
                    $videoEntity->setUser($entityManager->getRepository(User::class)->find(2));
                    $videoEntity->setCategorie($entityManager->getRepository(Categorie::class)->find(7));
                    $videoEntity->setTitle($title);
                    $videoEntity->setFilename($filename);
                    $videoEntity->setThumbnailFilename($thumbnailFilename);
                    $videoEntity->setCreatedAt(new \DateTime());

                    $entityManager->persist($videoEntity);
                    $entityManager->flush();

                    $downloadStatus[] = ['link' => $link, 'status' => 'success', 'title' => $title];
                }
            } catch (\Exception $e) {
                $logger->error('Une erreur inattendue est survenue', ['link' => $link, 'exception' => $e->getMessage()]);
                $downloadStatus[] = ['link' => $link, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }

        return new JsonResponse(['status' => 'completed', 'results' => $downloadStatus], 200);
    }


    #[Route('/new', name: 'app_api_youtube_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $video = new Video();
        $form = $this->createForm(APIType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($video);
            $entityManager->flush();

            return $this->redirectToRoute('app_api_youtube_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('api_youtube/new.html.twig', [
            'video' => $video,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_api_youtube_show', methods: ['GET'])]
    public function show(Video $video): Response
    {
        return $this->render('api_youtube/show.html.twig', [
            'video' => $video,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_api_youtube_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Video $video, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(APIType::class, $video);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_api_youtube_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('api_youtube/edit.html.twig', [
            'video' => $video,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_api_youtube_delete', methods: ['POST'])]
    public function delete(Request $request, Video $video, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$video->getId(), $request->get('_token'))) {
            $entityManager->remove($video);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_api_youtube_index', [], Response::HTTP_SEE_OTHER);
    }
}
