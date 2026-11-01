<?php

namespace App\Stock\UI\Controller;

use App\Shared\Application\Service\TenantContext;
use App\Shared\UI\Controller\ApiInterface;
use App\Stock\Application\Service\StockService;
use App\Stock\Domain\Entity\Article;
use App\Stock\Domain\Entity\CategorieArticle;
use App\Stock\Domain\Entity\MouvementStock;
use App\Stock\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use App\Security\Domain\Attribute\RequirePermission;

#[Route('/api/stock/articles')]
class ApiArticleController extends ApiInterface
{
    private const GROUPS = ['article:read', 'categorieArticle:read', 'stock', 'ref'];

    #[RequirePermission('articles', 'R', or: [['demandes', 'C']])]
    #[Route('', name: 'api_stock_articles_index', methods: ['GET'])]
    public function index(Request $request, TenantContext $tenant, ArticleRepositoryInterface $repo): JsonResponse
    {
        $list = $repo->search(
            $tenant->requireCentre(),
            $request->query->get('search'),
            $request->query->getInt('categorie') ?: null,
            $request->query->get('statut')
        );
        return $this->responseData($list, [], ['total' => count($list)], self::GROUPS);
    }

    #[RequirePermission('articles', 'R', or: [['demandes', 'C']])]
    #[Route('/{id}', name: 'api_stock_articles_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, TenantContext $tenant, ArticleRepositoryInterface $repo): JsonResponse
    {
        $article = $repo->findOneInCentre($id, $tenant->requireCentre());
        return $article ? $this->responseData($article, [], [], self::GROUPS) : $this->errorResponse(null, 'Article introuvable.', 404);
    }

    #[RequirePermission('articles', 'C')]
    #[Route('', name: 'api_stock_articles_create', methods: ['POST'])]
    public function create(Request $request, TenantContext $tenant, ArticleRepositoryInterface $repo, EntityManagerInterface $em, StockService $stock): JsonResponse
    {
        $data = $this->body($request);
        $centre = $tenant->requireCentre();
        if (empty(trim((string) ($data['codeSku'] ?? ''))) || empty(trim((string) ($data['designation'] ?? '')))) {
            return $this->errorResponse(null, 'Le code et la désignation sont obligatoires.', 400);
        }
        if ($em->getRepository(Article::class)->findOneBy(['centre' => $centre, 'codeSku' => trim($data['codeSku'])])) {
            return $this->errorResponse(null, 'Ce code article existe déjà dans ce centre.', 400);
        }

        $article = (new Article())
            ->setEntreprise($tenant->requireEntreprise())
            ->setCentre($centre)
            ->setCodeSku(trim($data['codeSku']))
            ->setDesignation(trim($data['designation']));
        $this->hydrate($article, $data, $tenant, $em);

        $em->wrapInTransaction(function () use ($article, $data, $repo, $stock) {
            $repo->add($article, true);
            $initial = (int) ($data['quantiteStock'] ?? 0);
            if ($initial > 0) {
                $stock->appliquer($article, $initial, MouvementStock::AJUSTEMENT, null, 'Stock initial');
            }
        });

        return $this->setStatusCode(201)->responseData($article, [], [], self::GROUPS);
    }

    #[RequirePermission('articles', 'U')]
    #[Route('/{id}', name: 'api_stock_articles_update', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, TenantContext $tenant, ArticleRepositoryInterface $repo, EntityManagerInterface $em): JsonResponse
    {
        $article = $repo->findOneInCentre($id, $tenant->requireCentre());
        if (!$article) {
            return $this->errorResponse(null, 'Article introuvable.', 404);
        }

        $data = $this->body($request);
        if (!empty($data['designation'])) $article->setDesignation(trim($data['designation']));
        if (!empty($data['codeSku']) && trim($data['codeSku']) !== $article->getCodeSku()) {
            if ($em->getRepository(Article::class)->findOneBy(['centre' => $article->getCentre(), 'codeSku' => trim($data['codeSku'])])) {
                return $this->errorResponse(null, 'Ce code article existe déjà dans ce centre.', 400);
            }
            $article->setCodeSku(trim($data['codeSku']));
        }
        $this->hydrate($article, $data, $tenant, $em);
        $em->flush();

        return $this->responseData($article, [], [], self::GROUPS);
    }

    #[RequirePermission('articles', 'D')]
    #[Route('/{id}', name: 'api_stock_articles_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, TenantContext $tenant, ArticleRepositoryInterface $repo, EntityManagerInterface $em): JsonResponse
    {
        $article = $repo->findOneInCentre($id, $tenant->requireCentre());
        if (!$article) {
            return $this->errorResponse(null, 'Article introuvable.', 404);
        }
        $article->setIsActive(false);
        $em->flush();

        return $this->setMessage('Article supprimé.')->responseData([]);
    }

    /** Ajustement d'inventaire : fixe le stock à la quantité comptée. */
    #[RequirePermission(action: 'stock.ajuster')]
    #[Route('/{id}/ajustement', name: 'api_stock_articles_ajustement', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function ajuster(int $id, Request $request, TenantContext $tenant, ArticleRepositoryInterface $repo, StockService $stock, EntityManagerInterface $em): JsonResponse
    {
        $article = $repo->findOneInCentre($id, $tenant->requireCentre());
        if (!$article) {
            return $this->errorResponse(null, 'Article introuvable.', 404);
        }

        $data = $this->body($request);
        if (!isset($data['quantiteComptee']) || (int) $data['quantiteComptee'] < 0) {
            return $this->errorResponse(null, 'La quantité comptée est obligatoire et positive.', 400);
        }
        $delta = (int) $data['quantiteComptee'] - $article->getQuantiteStock();
        if ($delta === 0) {
            return $this->responseData($article, [], [], self::GROUPS);
        }

        try {
            $stock->appliquer($article, $delta, MouvementStock::AJUSTEMENT, 'INVENTAIRE', $data['commentaire'] ?? 'Ajustement d\'inventaire');
            $em->flush();
        } catch (\DomainException $e) {
            return $this->errorResponse(null, $e->getMessage(), 400);
        }

        return $this->responseData($article, [], [], self::GROUPS);
    }

    #[RequirePermission('articles', 'R')]
    #[Route('/{id}/mouvements', name: 'api_stock_articles_mouvements', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function mouvements(int $id, TenantContext $tenant, ArticleRepositoryInterface $repo, EntityManagerInterface $em): JsonResponse
    {
        $article = $repo->findOneInCentre($id, $tenant->requireCentre());
        if (!$article) {
            return $this->errorResponse(null, 'Article introuvable.', 404);
        }
        $list = $em->getRepository(MouvementStock::class)->findBy(['article' => $article], ['dateMouvement' => 'DESC', 'id' => 'DESC'], 200);

        return $this->responseData($list, [], [], ['mouvementStock:read']);
    }

    private function hydrate(Article $article, array $data, TenantContext $tenant, EntityManagerInterface $em): void
    {
        if (!empty($data['unite'])) $article->setUnite($data['unite']);
        if (isset($data['seuilAlerteMin'])) $article->setSeuilAlerteMin(max(0, (int) $data['seuilAlerteMin']));
        if (isset($data['seuilReapproCible'])) $article->setSeuilReapproCible(max(0, (int) $data['seuilReapproCible']));
        if (isset($data['prixMoyenHt']) && is_numeric($data['prixMoyenHt'])) $article->setPrixMoyenHt(number_format((float) $data['prixMoyenHt'], 2, '.', ''));
        if (array_key_exists('description', $data)) $article->setDescription($data['description']);
        if (array_key_exists('categorie', $data)) {
            $categorie = $data['categorie']
                ? $em->getRepository(CategorieArticle::class)->findOneBy(['id' => (int) $data['categorie'], 'entreprise' => $tenant->requireEntreprise()])
                : null;
            $article->setCategorie($categorie);
        }
    }
}
