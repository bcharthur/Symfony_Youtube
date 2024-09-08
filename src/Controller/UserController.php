<?php

namespace App\Controller;

use App\Entity\Abonnement;
use App\Entity\User;
use App\Form\ProfilePictureType;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findAll(),
        ]);
    }

    // src/Controller/UserController.php

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, UserRepository $userRepository): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Générer un nom d'utilisateur unique
            $username = 'user' . random_int(10000000, 99999999);
            while ($userRepository->findOneBy(['username' => $username])) {
                $username = 'user' . random_int(10000000, 99999999);
            }
            $user->setUsername($username);

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $profilePictureFile */
            $profilePictureFile = $form->get('profilePictureFile')->getData();

            if ($profilePictureFile) {
                $user->setProfilePictureFile($profilePictureFile);
            }

            // Flush the changes to the database
            $entityManager->flush();

            // Important: Unset the file to avoid serialization issues
            $user->setProfilePictureFile(null);

            return $this->redirectToRoute('app_user_profile', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }



    #[Route('/{id}', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($user);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }


    #[Route('/check-username', name: 'app_check_username', methods: ['POST'])]
    public function checkUsername(Request $request, UserRepository $userRepository): JsonResponse
    {
        $username = $request->request->get('username');
        $userExists = $userRepository->findOneBy(['username' => $username]) !== null;

        return new JsonResponse(['exists' => $userExists]);
    }


    #[Route('/check-email', name: 'app_check_email', methods: ['POST'])]
    public function checkEmail(Request $request, UserRepository $userRepository): JsonResponse
    {
        $email = $request->request->get('email');
        $emailExists = $userRepository->findOneBy(['email' => $email]) !== null;

        return new JsonResponse(['exists' => $emailExists]);
    }

    #[Route('/{id}/edit-profile-picture', name: 'app_user_edit_profile_picture', methods: ['GET', 'POST'])]
    public function editProfilePicture(Request $request, User $user, EntityManagerInterface $entityManager): Response
    {

        $form = $this->createForm(ProfilePictureType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $profilePictureFile */
            $profilePictureFile = $form->get('profilePictureFile')->getData();

            if ($profilePictureFile) {
                $user->setProfilePictureFile($profilePictureFile);
            }

            // Flush the changes to the database
            $entityManager->flush();

            // Important: Unset the file to avoid serialization issues
            $user->setProfilePictureFile(null);

            return $this->redirectToRoute('app_user_profile', ['id' => $user->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/edit_profile_picture.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/abonnement', name: 'app_user_abonnement', methods: ['POST'])]
    public function abonnement(User $cible, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Vous devez être connecté pour vous abonner.'], JsonResponse::HTTP_FORBIDDEN);
        }

        $abonnementRepo = $entityManager->getRepository(Abonnement::class);
        $abonnement = $abonnementRepo->findOneBy(['abonne' => $user, 'cible' => $cible]);

        if ($abonnement) {
            // Désabonnement
            $entityManager->remove($abonnement);
            $entityManager->flush();
            return new JsonResponse(['abonne' => false, 'subscribers_count' => $abonnementRepo->count(['cible' => $cible])]);
        } else {
            // Abonnement
            $newAbonnement = new Abonnement();
            $newAbonnement->setAbonne($user);
            $newAbonnement->setCible($cible);
            $entityManager->persist($newAbonnement);
            $entityManager->flush();

            return new JsonResponse(['abonne' => true, 'subscribers_count' => $abonnementRepo->count(['cible' => $cible])]);
        }
    }


    #[Route('/{id}/abonnements', name: 'app_user_abonnements')]
    public function viewAbonnements(User $user, EntityManagerInterface $entityManager): Response
    {
        $abonnements = $entityManager->getRepository(Abonnement::class)->findBy(['abonne' => $user]);

        return $this->render('abonnement/index.html.twig', [
            'abonnements' => $abonnements,
            'user' => $user,
        ]);
    }

}
