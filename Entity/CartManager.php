<?php
/**
 * (c) Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Vespolina\CartBundle\Entity;

use Symfony\Component\DependencyInjection\Container;
use Doctrine\ORM\EntityManager;
use Vespolina\Cart\Pricing\CartPricingProviderInterface;
use Vespolina\CartBundle\Entity\Cart;
use Vespolina\Entity\Order\CartInterface;
use Vespolina\Entity\Order\ItemInterface;
use Vespolina\Entity\ProductInterface;
use Vespolina\Cart\Manager\CartManager as BaseCartManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @author Daniel Kucharski <daniel@xerias.be>
 * @author Richard Shank <develop@zestic.com>
 */
class CartManager extends BaseCartManager
{
    protected $cartRepo;
    protected $em;
    protected $session;

    public function __construct(EntityManager $em, SessionInterface $session, CartPricingProviderInterface $pricingProvider = null, $cartClass, $cartItemClass, $cartEvents, $eventClass, $eventDispatcher)
    {
        $this->em = $em;

        $this->cartRepo = $this->em->getRepository($cartClass);
        $this->session = $session;

        parent::__construct($pricingProvider, $cartClass, $cartItemClass, $cartEvents, $eventClass, $eventDispatcher);
    }

    public function addProductToCart(CartInterface $cart, ProductInterface $product, array $options = null, $quantity = null)
    {
        $item = parent::addProductToCart($cart, $product, $options, $quantity);

        // todo: just update this cart, don't flush everything
        if ($cart->getId() !== $cart->getId()) {
            $this->em->createQueryBuilder($this->cartClass)
                ->findAndUpdate()
                ->field('id')->equals($cart->getId())
                ->field('items')->set($cart->getItems())
                ->getQuery()
                ->execute()
            ;
        } else {
            $this->updateCart($cart);
        }
    }

    /**
     * @inheritdoc
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->cartRepo->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function findCartById($id)
    {
        return $this->cartRepo->find($id);
    }

    public function findOpenCartByOwner($owner)
    {

        if ($owner) {

            $q = $this->em->createQueryBuilder($this->cartClass)
                            ->select('c')
                            ->from('Vespolina\CartBundle\Entity\Cart', 'c')
                            ->where('c.owner = ?1')
                            ->andWhere('c.state = ?2')
                            ->getQuery();
            $q->setMaxResults(1);   //Temp
            $q->setParameters(array(1 => $owner, 2 => Cart::STATE_OPEN));

            return $q->getSingleResult();
        }
    }

    public function getActiveCart($owner = null)
    {
        if ($cart = $this->session->get('vespolina_cart')) {
            return $cart;
        }
        if (!$owner) {
            $cart = $this->createCart();
            $this->updateCart($cart);
        } elseif (!$cart = $this->findOpenCartByOwner($owner)) {
            $cart = $this->createCart();
            $cart->setOwner($owner);
            $this->updateCart($cart);
        }
        $this->session->set('vespolina_cart', $cart);

        return $cart;
    }

    /**
     * @inheritdoc
     */
    public function updateCart(CartInterface $cart, $andPersist = true)
    {
        parent::updateCart($cart, $andPersist);

        $this->em->persist($cart);
        if ($andPersist) {
            $this->em->flush($cart);
        }
    }
}
