<?php

namespace App\Controller;

use App\Entity\Document;
use App\Entity\Folder;
use App\Form\DocumentType;
use App\Repository\FolderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Service\DirectoryNamer;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class DocumentController extends AbstractController
{
    private $security;
    private $documentsDirectory;
    private $directoryNamer;

    public function __construct(Security $security,string $documentsDirectory, DirectoryNamer $directoryNamer)
    {
        $this->security = $security;
        $this->documentsDirectory = $documentsDirectory;
        $this->directoryNamer = $directoryNamer;
    }


    // Afficher la liste des documents dans un dossier
    #[Route('/folder/{id}', name: 'folder_show')]
    public function show(Folder $folder, FolderRepository $folderRepository): Response
    {
        // Vérifier que l'utilisateur connecté est le propriétaire du dossier
        if ($folder->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit d\'accéder à ce dossier.');
        }

        return $this->render('folder/show.html.twig', [
            'folder' => $folder,
            'subfolders' => $folderRepository->findBy(['parent' => $folder]),
        ]);
    }

    #[Route('/folder/{id}/upload', name: 'document_upload', methods: ['GET', 'POST'])]
    public function upload(Folder $folder, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que l'utilisateur connecté est le propriétaire du dossier
        if ($folder->getOwner() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n\'avez pas le droit d\'accéder à ce dossier.');
        }

        // Gestion de l'upload de fichier
        $document = new Document();
        $document->setFolder($folder);
        $document->setOwner($this->getUser());

        $form = $this->createForm(DocumentType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($document);
            $entityManager->flush();

            $this->addFlash('success', 'Fichier uploadé avec succès.');

            return $this->redirectToRoute('folder_show', ['id' => $folder->getId()]);
        }

        return $this->render('document/upload.html.twig', [
            'folder' => $folder,
            'form' => $form->createView(),
        ]);
    }


    #[Route('/{id}/preview', name: 'document_preview', methods: ['GET'])]
    public function preview(Document $document): Response
    {
        $response = $this->getResponse($document);

        return $response;
    }

    #[Route('/{id}/download', name: 'document_download', methods: ['GET'])]
    public function download(Document $document): Response
    {
        $response = $this->getResponse($document);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $document->getFileName() . '"');

        return $response;
    }

    /**
     * @param Document $document
     * @return Response
     */
    public function getResponse(Document $document): Response
    {
        $mapping = new PropertyMapping('file', 'file');

        $directoryName = $this->directoryNamer->directoryName($document, $mapping);

        $filePath = $this->documentsDirectory . '/' . $directoryName . '/' . $document->getFileName();

        if (!file_exists($filePath)) {
            throw $this->createNotFoundException('The file does not exist');
        }

        $response = new Response();
        $response->setContent(file_get_contents($filePath));
        $response->headers->set('Content-Type', mime_content_type($filePath));
        return $response;
    }

    #[Route('/{id}/delete', name: 'document_delete', methods: ['GET'])]
    public function delete(Request $request, Document $document, EntityManagerInterface $entityManager): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $folder = $document->getFolder();
        $response = $this->getResponse($document);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $document->getFileName() . '"');



            $entityManager->remove($document);
            $entityManager->flush();

            $this->addFlash('success', 'Le document a été supprimé avec succès.');

            if ($folder === null) {
                return $this->redirectToRoute('folder_index');
            }
        return $this->redirectToRoute('folder_show', ['id' => $document->getFolder()->getId()]);





    }


}
