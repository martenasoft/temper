<?php

namespace App\Controller;

use App\Entity\ProjectMessage;
use App\Form\ProjectMessageType;
use App\Repository\ProjectMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/project/message')]
final class ProjectMessageController extends AbstractController
{
    public function __construct(
        private ProjectMessageRepository $projectMessageRepository,
        private EntityManagerInterface $entityManager
    )
    {

    }
    #[Route(name: 'app_project_message_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('project_message/index.html.twig', [
            'project_messages' => $this->projectMessageRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_project_message_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $projectMessage = new ProjectMessage();
        $form = $this->createForm(ProjectMessageType::class, $projectMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($projectMessage);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_project_message_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_message/new.html.twig', [
            'project_message' => $projectMessage,
            'form' => $form,
        ]);
    }

    #[Route('/show/{uuid}', name: 'app_project_message_show', methods: ['GET'])]
    public function show(string $uuid): Response
    {
        $projectMessage = $this->getEntity($uuid);

        return $this->render('project_message/show.html.twig', [
            'project_message' => $projectMessage,
        ]);
    }

    #[Route('/edit/{uuid}', name: 'app_project_message_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $uuid, EntityManagerInterface $entityManager): Response
    {
        $projectMessage = $this->getEntity($uuid);
        $form = $this->createForm(ProjectMessageType::class, $projectMessage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_project_message_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('project_message/edit.html.twig', [
            'project_message' => $projectMessage,
            'form' => $form,
        ]);
    }

    #[Route('/delete/{uuid}', name: 'app_project_message_delete', methods: ['POST'])]
    public function delete(string $uuid, EntityManagerInterface $entityManager): Response
    {
        $projectMessage = $this->getEntity($uuid);
        $entityManager->remove($projectMessage);
        $entityManager->flush();
        return $this->redirectToRoute('app_project_message_index', [], Response::HTTP_SEE_OTHER);
    }

    private function getEntity(string $uuid): ProjectMessage
    {
        $projectMessage = $this->projectMessageRepository->findOneBy(['uuid' => $uuid]);
        if (!$projectMessage) {
            throw $this->createNotFoundException('Unable to find ProjectMessage entity.');
        }

        return $projectMessage;
    }
}
