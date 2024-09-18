<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use App\Service\VersioningService;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class BookController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des livres
     * 
     * @OA\Response(
     *      response=200,
     *      description="Retourne la liste des livres",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *      )
     * )
     * @OA\Parameter(
     *      name="page",
     *      in="query",
     *      description="La page que l'on veut récupérer",
     *      @OA\Schema(type="int")
     * )
     * 
     * @OA\Parameter(
     *      name="limit",
     *      in="query",
     *      description="Le nombre d'éléments que l'on veut récupérer",
     *      @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Books")
     * 
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     * 
     */
    #[Route('/api/books', name: 'book', methods: ['GET'])]
    public function getBookList(BookRepository $bookRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, VersioningService $versioningService): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $version = $versioningService->getVersion();
        $idCache = "getBookList-" . $page . "-" . $limit;
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $context->setVersion($version);
        $jsonBookList = $cache->get($idCache, function (ItemInterface $item) use ($bookRepository, $page, $limit, $serializer, $context) {
            $item->tag("booksCache");
            $bookList = $bookRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($bookList, 'json', $context);
        });

        // if (!is_string($jsonBookList)) {
        //     $jsonBookList = $serializer->serialize($jsonBookList, 'json', $context);
        // }

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récupérer les détails de seulement 1 livre
     * 
     * @OA\Get(
     *      path="/api/book/{id}",
     *      summary="Cette méthode permet de récupérer les détails d'un livre",
     *      @OA\Response(
     *          response=200,
     *          description="Retourne les détails du livre",
     *          @OA\JsonContent(
     *              type="object",
     *              ref=@Model(type=Book::class, groups={"getBooks"})
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="L'ID du livre",
     *          @OA\Schema(type="integer")
     *      ),
     * )
     * @OA\Tag(name="Books")
     * 
     * @param Book $book
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route('/api/book/{id}', name: 'detailBook', methods: ['GET'])]
    public function getDetailBook(Book $book, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $version = $versioningService->getVersion();
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $context->setVersion($version);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Cette méthode permet de supprimer seulement 1 livre
     * 
     * @OA\Response(
     *      response=200,
     *      description="Supprime un livre",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *      )
     * )
     * 
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      required=true,
     *      description="L'id du livre",
     *      @OA\Schema(type="string")
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     * 
     */
    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour supprimer un livre")]
    public function deleteBook(Book $book, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet de créer un livre
     * 
     * @OA\Response(
     *      response=200,
     *      description="Créer un livre",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *      )
     * )
     * 
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="title", type="string"),
     *          @OA\Property(property="coverText", type="string"),
     *          @OA\Property(property="idAuthor", type="int")
     *      )
     * )
     * 
     * @OA\Tag(name="Books")
     * 
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     * 
     */
    #[Route('/api/books', name: "createBook", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour créer un livre")]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator): JsonResponse
    {
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');

        // On vérifie les erreurs
        $errors = $validator->validate($book);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            // Exception personnalisé
            // throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "La requête est invalide");
        }

        // Récupération de l'ensemble des données envoyées sous forme de tableau
        $content = $request->toArray();

        // Récupération de l'idAuthor, s'il n'est pas défini, alors on met -1 par défaut
        $idAuthor = $content['idAuthor'] ?? -1;

        // On cherche l'auteur qui correspond et on l'assigne au livre
        // Si "find" ne trouve pas l'auteur, alors null sera retourné
        $book->setAuthor($authorRepository->find($idAuthor));

        $em->persist($book);
        $em->flush();
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $jsonBook = $serializer->serialize($book, 'json', $context);

        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de mettre à jour un livre
     * 
     * @OA\Response(
     *      response=200,
     *      description="Mettre à jour un livre",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Book::class, groups={"getBooks"}))
     *      )
     * )
     * 
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="title", type="string"),
     *          @OA\Property(property="coverText", type="string"),
     *          @OA\Property(property="idAuthor", type="int")
     *      )
     * )
     * 
     * @OA\Tag(name="Books")
     */
    #[Route('api/book/{id}', name: "updateBook", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour mettre à jour un Livre")]
    public function updateBook(Request $request, SerializerInterface $serializer, Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository, TagAwareCacheInterface $cache, ValidatorInterface $validator): JsonResponse
    {
        $newBook = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());
        $errors = $validator->validate($currentBook);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $currentBook->setAuthor($authorRepository->find($idAuthor));
        $em->persist($currentBook);
        $em->flush();
        // On vide le cache
        $cache->invalidateTags(["booksCache"]);
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
