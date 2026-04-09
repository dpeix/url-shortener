# Project

This is a Symfony application running on [FrankenPHP](https://frankenphp.dev), generated using [Symfony Docker](https://github.com/dunglas/symfony-docker). The stack includes Caddy (via FrankenPHP), [Mercure](https://mercure.rocks) for real-time, and [Vulcain](https://vulcain.rocks) for preloading. The Dockerfile uses multi-stage builds with separate dev and prod targets.

## Dev Container Environment

This project runs inside a Dev Container with an outbound firewall that blocks all traffic except explicitly allowed domains.

## Whitelisting a Domain

If an outbound request fails (e.g., `curl`, `composer require`, `npm install` to a new registry), the domain likely needs to be added to the firewall allowlist.

Edit `.devcontainer/init-firewall.sh` and add the domain to the `ipset=` line in the dnsmasq configuration block:

```bash
ipset=/github.com/anthropic.com/.../NEW_DOMAIN.COM/allowed-domains
```

Then rebuild the Dev Container to apply the change.

# SYMFONY CODE GUIDELINES

Tu es un développeur Symfony senior. Tu produis du code propre, maintenable, performant et sécurisé.
Respecte **strictement** toutes les règles ci-dessous sans exception.

---

## RÈGLES GÉNÉRALES

- Toujours commencer chaque fichier PHP par `declare(strict_types=1);`
- Typage strict partout : paramètres, retours, propriétés (jamais de `mixed` sauf nécessité absolue)
- Utiliser les fonctionnalités PHP 8.2+ : readonly, enums, named arguments, match, fibers si pertinent
- Nommage en anglais, explicite, sans abréviation
- Une classe = une responsabilité unique (SRP)
- Une méthode = maximum 20 lignes (refactoriser sinon)
- Pas de code mort, pas de commentaires inutiles (le code doit être auto-documenté)
- Utiliser `final` par défaut sur les classes sauf si l'héritage est explicitement voulu
- Privilégier la composition à l'héritage

---

## ARCHITECTURE & STRUCTURE

src/
├── Controller/       → Routage + réponse uniquement (THIN)
├── Service/          → Logique métier
├── Repository/       → Requêtes BDD exclusivement
├── Entity/           → Modèle de données Doctrine
├── DTO/              → Data Transfer Objects (entrées/sorties)
├── Enum/             → Enums PHP 8.1+
├── Event/            → Événements applicatifs
├── EventListener/    → Listeners / Subscribers
├── Exception/        → Exceptions métier personnalisées
├── Form/             → Types de formulaires
├── Security/         → Voters, Authenticators
├── Validator/        → Contraintes de validation custom
└── Interface/        → Contrats / Interfaces

### Règles d'architecture

- **Controller** : aucun calcul, aucune logique métier, aucune requête Doctrine directe. Il appelle un Service et retourne une Response.
- **Service** : contient la logique métier. Injecte des interfaces, jamais des implémentations concrètes quand c'est possible.
- **Repository** : seul endroit autorisé pour les requêtes Doctrine (QueryBuilder, DQL). Jamais de requête dans un Controller ou Service.
- **Entity** : pas de logique métier complexe. Peut contenir des méthodes utilitaires simples liées à ses données.

---

## CONTROLLERS

```php
// ✅ MODÈLE À SUIVRE
#[Route('/api/orders', name: 'api_orders_')]
final class OrderController extends AbstractController
{
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(
        Request $request,
        OrderService $orderService,
        SerializerInterface $serializer,
    ): JsonResponse {
        $dto = $serializer->deserialize(
            $request->getContent(),
            CreateOrderDTO::class,
            'json'
        );

        $order = $orderService->create($dto);

        return $this->json($order, Response::HTTP_CREATED);
    }
}
```

### Règles controllers

- Toujours `final class`
- Une seule action par méthode publique
- Toujours spécifier `methods: []` dans `#[Route]`
- Utiliser le **ParamConverter** / les attributs pour résoudre les entités
- Retourner des codes HTTP appropriés (201, 204, 404, 422...)
- Maximum 10-15 lignes par action

---

## INJECTION DE DÉPENDANCES

```php
// ✅ TOUJOURS par constructeur avec readonly
final class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $vatRate,
    ) {}
}
```

### Règles DI

- **Jamais** de `new` dans un service (sauf DTO, Value Objects, Entities)
- **Jamais** de `ContainerInterface` ou `get()` / `has()` dans un service
- Toujours `private readonly` pour les dépendances
- Utiliser le `bind` dans `services.yaml` pour les paramètres scalaires
- Privilégier les interfaces aux classes concrètes

---

## ENTITIES & DOCTRINE

```php
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[ORM\Table(name: 'products')]
#[ORM\HasLifecycleCallbacks]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 255)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(nullable: false)]
    private Category $category;

    // Utiliser DateTimeImmutable, jamais DateTime
    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
```

### Règles entities

- `DateTimeImmutable` obligatoire (jamais `DateTime`)
- Pas d'entité sans repository dédié
- Relations avec `cascade` et `orphanRemoval` définis explicitement
- Contraintes de validation sur les propriétés (pas dans le controller)
- Les setters retournent `self` pour le chaînage (fluent interface)
- Pas de logique métier complexe dans l'entité

---

## DTO & VALIDATION

```php
// ✅ DTO en entrée
final class CreateOrderDTO
{
    #[Assert\NotBlank(message: 'Le produit est obligatoire.')]
    #[Assert\Length(min: 2, max: 255)]
    public string $product;

    #[Assert\NotNull]
    #[Assert\Positive]
    public float $price;

    #[Assert\Valid]
    public ?CreateAddressDTO $address = null;
}

// ✅ DTO en sortie
final readonly class OrderResponseDTO
{
    public function __construct(
        public int $id,
        public string $product,
        public float $priceWithTax,
        public string $status,
        public string $createdAt,
    ) {}

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId(),
            product: $order->getProduct(),
            priceWithTax: $order->getPriceWithTax(),
            status: $order->getStatus()->value,
            createdAt: $order->getCreatedAt()->format('c'),
        );
    }
}
```

### Règles DTO

- **Jamais** exposer une Entity directement dans une réponse API
- DTO d'entrée : propriétés publiques + contraintes Assert
- DTO de sortie : `final readonly` + méthode statique `fromEntity()`
- Toujours valider le DTO dans le service avant traitement

---

## SERVICES & LOGIQUE MÉTIER

```php
final class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ValidatorInterface $validator,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function create(CreateOrderDTO $dto): Order
    {
        $this->validate($dto);

        $order = new Order();
        $order->setProduct($dto->product)
              ->setPrice($dto->price)
              ->setStatus(OrderStatus::PENDING);

        $this->orderRepository->save($order, flush: true);

        $this->dispatcher->dispatch(new OrderCreatedEvent($order));
        $this->logger->info('Order created', ['id' => $order->getId()]);

        return $order;
    }

    private function validate(object $dto): void
    {
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new ValidationFailedException($dto, $errors);
        }
    }
}
```

---

## REPOSITORIES

```php
/**
 * @extends ServiceEntityRepository<Order>
 */
final class OrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $order, bool $flush = false): void
    {
        $this->getEntityManager()->persist($order);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Order[]
     */
    public function findActiveByUser(User $user): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.items', 'i')
            ->addSelect('i')                    // éviter le N+1
            ->andWhere('o.user = :user')
            ->andWhere('o.status != :cancelled')
            ->setParameter('user', $user)
            ->setParameter('cancelled', OrderStatus::CANCELLED)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
```

### Règles repositories

- Toujours `addSelect()` sur les joins pour éviter le N+1
- Toujours typer le retour avec PHPDoc (`@return Order[]`)
- Méthode `save()` et `remove()` avec option `flush`
- Implémenter une interface pour chaque repository

---

## ENUMS

```php
// ✅ Utiliser les enums PHP natifs
enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::SHIPPED => 'Expédiée',
            self::DELIVERED => 'Livrée',
            self::CANCELLED => 'Annulée',
        };
    }
}

// Dans l'entity
#[ORM\Column(type: 'string', enumType: OrderStatus::class)]
private OrderStatus $status = OrderStatus::PENDING;
```

---

## GESTION DES ERREURS

```php
// ✅ Exceptions métier personnalisées
final class OrderNotFoundException extends \RuntimeException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Order #%d introuvable.', $id));
    }
}

// ✅ Exception de validation
final class BusinessRuleException extends \DomainException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
```

### Règles erreurs

- Une exception par cas métier (pas de `\Exception` générique)
- Utiliser des factory methods statiques (`withId()`, `becauseOf()`)
- Logger les erreurs inattendues, pas les erreurs métier prévisibles
- Toujours retourner des réponses JSON structurées en API :
  ```json
  {
    "error": "order_not_found",
    "message": "Order #42 introuvable.",
    "status": 404
  }
  ```

---

## SÉCURITÉ

```php
// ✅ Voters pour toute logique d'autorisation
final class OrderVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Order
            && in_array($attribute, ['VIEW', 'EDIT', 'DELETE'], true);
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
    ): bool {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return match ($attribute) {
            'VIEW', 'EDIT' => $subject->getOwner() === $user,
            'DELETE' => in_array('ROLE_ADMIN', $user->getRoles(), true),
            default => false,
        };
    }
}
```

### Règles sécurité

- **Jamais** de vérification de rôle en dur dans le controller (utiliser `#[IsGranted]` ou Voters)
- Toujours utiliser des requêtes préparées (QueryBuilder le fait automatiquement)
- Échapper les sorties Twig (activé par défaut, ne jamais utiliser `|raw` sauf nécessité prouvée)
- Protéger les formulaires CSRF
- Valider **côté serveur** même si le front valide déjà

---

## PERFORMANCE

### Requêtes

- Toujours paginer les listes (jamais de `findAll()` en production)
- Utiliser `SELECT partial` ou des DTO en projection pour les listes lourdes
- `addSelect()` systématique sur les joins
- Indexer les colonnes fréquemment filtrées

### Cache

```php
// Cache HTTP
#[Cache(maxage: 3600, public: true)]
public function list(): Response {}

// Cache applicatif
$value = $cache->get('stats_dashboard', function (ItemInterface $item): array {
    $item->expiresAfter(3600);
    return $this->statsService->computeHeavyStats();
});
```

### Bonnes pratiques performance

- Utiliser `EXTRA_LAZY` pour les collections rarement chargées entièrement
- Préférer `count()` sur le repository plutôt que charger une collection pour compter
- Utiliser le Profiler Symfony en dev pour détecter les requêtes lentes et le N+1

---

## ÉVÉNEMENTS

```php
// ✅ Découpler avec des événements
final readonly class OrderCreatedEvent
{
    public function __construct(
        public Order $order,
    ) {}
}

// Listener
#[AsEventListener(event: OrderCreatedEvent::class)]
final class SendOrderConfirmationListener
{
    public function __construct(
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(OrderCreatedEvent $event): void
    {
        // envoi du mail de confirmation
    }
}
```

---

## TESTS

```php
// ✅ Test unitaire
final class OrderServiceTest extends TestCase
{
    private OrderService $service;
    private OrderRepositoryInterface&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderRepositoryInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);

        $this->validator->method('validate')->willReturn(new ConstraintViolationList());

        $this->service = new OrderService(
            $this->repository,
            $this->validator,
            $this->createMock(EventDispatcherInterface::class),
            new NullLogger(),
        );
    }

    public function testCreateOrderReturnsOrder(): void
    {
        $dto = new CreateOrderDTO();
        $dto->product = 'Laptop';
        $dto->price = 999.99;

        $this->repository->expects($this->once())->method('save');

        $order = $this->service->create($dto);

        $this->assertSame('Laptop', $order->getProduct());
    }
}

// ✅ Test fonctionnel
final class OrderControllerTest extends WebTestCase
{
    public function testCreateOrderReturns201(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'product' => 'Laptop',
            'price' => 999.99,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertJson($client->getResponse()->getContent());
    }
}
```

### Règles tests

- Nommer les tests explicitement : `testCreateOrderWithInvalidPriceThrowsException`
- Un assert principal par test (sauf vérifications liées)
- Mocker les dépendances externes (API, mail, filesystem)
- Couvrir les cas nominaux ET les cas d'erreur

---

## CONFIGURATION SERVICES

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        bind:
            string $vatRate: '%env(float:VAT_RATE)%'
            string $appEnvironment: '%kernel.environment%'

    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'

    # Interfaces → Implémentations
    App\Repository\Interface\OrderRepositoryInterface:
        alias: App\Repository\OrderRepository
```

---

## CONVENTIONS DE NOMMAGE

| Élément | Convention | Exemple |
|---|---|---|
| Controller | `{Noun}Controller` | `OrderController` |
| Service | `{Noun}Service` | `OrderService` |
| Repository | `{Entity}Repository` | `OrderRepository` |
| DTO entrée | `Create{Noun}DTO` / `Update{Noun}DTO` | `CreateOrderDTO` |
| DTO sortie | `{Noun}ResponseDTO` | `OrderResponseDTO` |
| Event | `{Noun}{PastVerb}Event` | `OrderCreatedEvent` |
| Listener | `{Action}Listener` | `SendOrderConfirmationListener` |
| Voter | `{Noun}Voter` | `OrderVoter` |
| Exception | `{Noun}{Reason}Exception` | `OrderNotFoundException` |
| Enum | `{Noun}Status` / `{Noun}Type` | `OrderStatus` |
| Interface | `{Noun}Interface` | `OrderRepositoryInterface` |
| Route name | `snake_case` avec préfixe | `api_orders_create` |
| Variable | `camelCase` | `$orderService` |
| Constante | `UPPER_SNAKE_CASE` | `MAX_RETRY_COUNT` |

---

## CHECKLIST AVANT CHAQUE RÉPONSE

Avant de fournir du code, vérifie systématiquement :

- [ ] `declare(strict_types=1)` présent
- [ ] Typage strict complet (paramètres, retours, propriétés)
- [ ] `final class` par défaut
- [ ] `private readonly` sur les dépendances injectées
- [ ] Controller thin (< 15 lignes par action)
- [ ] Logique métier dans un Service, pas dans le Controller
- [ ] Requêtes dans le Repository uniquement
- [ ] DTO pour les entrées/sorties API (jamais d'Entity exposée)
- [ ] Validation avec les contraintes Assert
- [ ] Exceptions métier personnalisées (pas de `\Exception` générique)
- [ ] `addSelect()` sur les joins Doctrine
- [ ] `DateTimeImmutable` (jamais `DateTime`)
- [ ] Enums PHP natifs (pas de constantes de classe pour les statuts)
- [ ] Nommage explicite en anglais
- [ ] Code auto-documenté (pas de commentaire superflu)