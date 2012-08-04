<?php
/**
 * (c) 2011-2012 Vespolina Project http://www.vespolina-project.org
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace Vespolina\CartBundle\Document;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Vespolina\Cart\Pricing\CartPricingProviderInterface;
use Vespolina\CartBundle\Document\Cart;
use Vespolina\Entity\Order\CartInterface;
use Vespolina\Entity\Order\ItemInterface;
use Vespolina\Entity\ProductInterface;
use Vespolina\Cart\Manager\CartManager as BaseCartManager;

/**
 * @author Daniel Kucharski <daniel@xerias.be>
 * @author Richard Shank <develop@zestic.com>
 */
class CartManager extends BaseCartManager
{
    protected $cartRepo;
    protected $dm;
    protected $primaryIdentifier;
    protected $session;

    public function __construct(DocumentManager $dm, SessionInterface $session, CartPricingProviderInterface $pricingProvider = null, $cartClass, $cartItemClass, $cartEvents, $eventDispatcher)
    {
        $this->dm = $dm;

        $this->cartRepo = $this->dm->getRepository($cartClass);
        $this->session = $session;

        parent::__construct($pricingProvider, $cartClass, $cartItemClass, $cartEvents, $eventDispatcher);
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

    /**
     * @inheritdoc
     */
    public function findOpenCartById($id)
    {
        return $this->dm->createQueryBuilder($this->cartClass)
            ->field('_id')->equals(new \MongoId($id))
            ->field('state')->equals(Cart::STATE_OPEN)
            ->getQuery()
            ->getSingleResult();
    }

    public function findOpenCartByOwner($owner)
    {
        return $this->dm->createQueryBuilder($this->cartClass)
            ->field('owner.$id')->equals(new \MongoId($owner->getId()))
            ->field('state')->equals(Cart::STATE_OPEN)
            ->getQuery()
            ->getSingleResult();
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

        $this->dm->persist($cart);
        if ($andPersist) {
            $this->dm->flush($cart);
        }
    }
}
