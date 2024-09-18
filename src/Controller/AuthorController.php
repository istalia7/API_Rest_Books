<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;

class AuthorController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des Auteurs
     * 
     * @OA\Response(
     *      response=200,
     *      description="Retourne la liste des Auteurs",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
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
     * @OA\Tag(name="Authors")
     * 
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     * 
     */
    #[Route('/api/authors', name: 'author', methods: ['GET'])]
    public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllAuthors-" . $page . "-" . $limit;
        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer, $context) {
            $item->tag("authorsCache");
            $authorList = $authorRepository->findAllWithPagination($page, $limit);
            return $serializer->serialize($authorList, 'json', $context);
        });

        // $listAuthors = $authorRepository->findAll();
        // $jsonAuthorList = $serializer->serialize($listAuthors, 'json', ['groups' => 'getAuthors']);
        return new JsonResponse($jsonAuthorList, Response::HTTP_OK, [], true);
    }


    /**
     * Cette méthode permet de récupérer les détails de seulement 1 Auteur
     * 
     * @OA\Get(
     *      path="/api/author/{id}",
     *      summary="Cette méthode permet de récupérer les détails d'un Auteur",
     *      @OA\Response(
     *          response=200,
     *          description="Retourne les détails de l'Auteur",
     *          @OA\JsonContent(
     *              type="object",
     *              ref=@Model(type=Author::class, groups={"getAuthors"})
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          required=true,
     *          description="L'id de l'Auteur",
     *          @OA\Schema(type="integer")
     *      ),
     * )
     * @OA\Tag(name="Authors")
     * 
     * @param Author $author
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route('api/author/{id}', name: 'detailAuthor', methods: ['GET'])]
    public function getDetailAuthor(Author $author, SerializerInterface $serializer): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Cette méthode permet de supprimer seulement 1 Auteur
     * 
     * @OA\Response(
     *      response=200,
     *      description="Supprime un Auteur",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
     *      )
     * )
     * 
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      required=true,
     *      description="L'id de l'auteur",
     *      @OA\Schema(type="integer")
     * )
     * 
     * 
     * @OA\Tag(name="Authors")
     * 
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     * 
     */
    #[Route('api/author/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour supprimer un Auteur")]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["authorsCache"]);
        $em->remove($author);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet de créer un Auteur
     * 
     * @OA\Response(
     *      response=200,
     *      description="Créer un Auteur",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Book::class, groups={"getAuthors"}))
     *      )
     * )
     * 
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="firstName", type="string"),
     *          @OA\Property(property="lastName", type="string")
     *      )
     * )
     * 
     * @OA\Tag(name="Authors")
     * 
     * @param AuthorRepository $authorRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     * 
     */
    #[Route('api/authors', name: "createAuthor", methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour créer un Auteur")]
    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): JsonResponse
    {
        $author = $serializer->deserialize($request->getContent(), Author::class, 'json');

        $errors = $validator->validate($author);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $em->persist($author);
        $em->flush();
        $context = SerializationContext::create()->setGroups(["getAuthors"]);
        $jsonAuthor = $serializer->serialize($author, 'json', $context);

        $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de mettre à jour un Auteur
     * 
     * @OA\Response(
     *      response=200,
     *      description="Mettre à jour un Auteur",
     *      @OA\JsonContent(
     *          type="array",
     *          @OA\Items(ref=@Model(type=Author::class, groups={"getAuthors"}))
     *      )
     * )
     * 
     * @OA\Parameter(
     *      name="id",
     *      in="path",
     *      required=true,
     *      description="L'id de l'auteur",
     *      @OA\Schema(type="integer")
     * )
     * 
     * @OA\RequestBody(
     *      required=true,
     *      @OA\JsonContent(
     *          type="object",
     *          @OA\Property(property="firstName", type="string"),
     *          @OA\Property(property="lastName", type="string"),
     *      )
     * )
     * 
     * @OA\Tag(name="Authors")
     */
    #[Route('api/author/{id}', name: "updateAuthor", methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: "Vous n'avez pas les droits suffisants pour mettre à jour un Auteur")]
    public function updateAuthor(Request $request, SerializerInterface $serializer, Author $currentAuthor, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $newAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json');
        $currentAuthor->setFirstName($newAuthor->getFirstName());
        $currentAuthor->setLastName($newAuthor->getLastName());
        $errors = $validator->validate($currentAuthor);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        // On vide le cache
        $cachePool->invalidateTags(['authorsCache']);
        $em->persist($currentAuthor);
        $em->flush();
        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
