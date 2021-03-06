<?php
/**********************************************************************************/
/*                                                                                */
/*      Thelia	                                                                  */
/*                                                                                */
/*      Copyright (c) OpenStudio                                                  */
/*      email : info@thelia.net                                                   */
/*      web : http://www.thelia.net                                               */
/*                                                                                */
/*      This program is free software; you can redistribute it and/or modify      */
/*      it under the terms of the GNU General Public License as published by      */
/*      the Free Software Foundation; either version 3 of the License             */
/*                                                                                */
/*      This program is distributed in the hope that it will be useful,           */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of            */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             */
/*      GNU General Public License for more details.                              */
/*                                                                                */
/*      You should have received a copy of the GNU General Public License         */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.      */
/*                                                                                */
/**********************************************************************************/

namespace CouponGiveProduct\Coupon\Type;

use Thelia\Action\ProductSaleElement;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Coupon\FacadeInterface;
use Thelia\Coupon\Type\CouponAbstract;
use Thelia\Model\CartItem;
use Thelia\Model\ProductSaleElements;
use Thelia\Model\ProductSaleElementsQuery;

/**
 * Created by JetBrains PhpStorm.
 * Date: 20/12/13
 * Time: 19:24 PM
 *
 * Allow to add a given product to the Customer cart for free
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class GiveProduct extends CouponAbstract
{
    /** ProductSaleElement id input name */
    const INPUT_PRODUCT_SALE_ELEMENT_ID_NAME = 'product_sale_element_id';
    /** Product quantity input name */
    const INPUT_QUANTITY_NAME = 'quantity';

    /** @var string Service Id  */
    protected $serviceId = 'thelia.coupon.type.give_product';

    /** @var int Product Sale Element id you wish to offer */
    protected $productSaleElementsId = 0;

    /** @var int Quantity of Free product given for a Coupon */
    protected $quantity = 1;

    /** @var array Extended Inputs to manage */
    protected $extendedInputs = array(
        self::INPUT_PRODUCT_SALE_ELEMENT_ID_NAME,
        self::INPUT_QUANTITY_NAME
    );

    /**
     * Set Coupon
     *
     * @param FacadeInterface $facade                     Provides necessary value from Thelia
     * @param string          $code                       Coupon code (ex: XMAS)
     * @param string          $title                      Coupon title (ex: Coupon for XMAS)
     * @param string          $shortDescription           Coupon short description
     * @param string          $description                Coupon description
     * @param array           $effects                    Coupon effects params
     * @param bool            $isCumulative               If Coupon is cumulative
     * @param bool            $isRemovingPostage          If Coupon is removing postage
     * @param bool            $isAvailableOnSpecialOffers If available on Product already
     *                                                    on special offer price
     * @param bool            $isEnabled                  False if Coupon is disabled by admin
     * @param int             $maxUsage                   How many usage left
     * @param \Datetime       $expirationDate             When the Code is expiring
     *
     * @return $this
     */
    public function set(
        FacadeInterface $facade,
        $code,
        $title,
        $shortDescription,
        $description,
        array $effects,
        $isCumulative,
        $isRemovingPostage,
        $isAvailableOnSpecialOffers,
        $isEnabled,
        $maxUsage,
        \DateTime $expirationDate
    )
    {
        // We use the default behavior we will extend
        parent::set(
            $facade, $code, $title, $shortDescription, $description, $effects, $isCumulative, $isRemovingPostage, $isAvailableOnSpecialOffers, $isEnabled, $maxUsage, $expirationDate
        );

        if (isset($effects[self::INPUT_PRODUCT_SALE_ELEMENT_ID_NAME])) {
            $this->productSaleElementsId = $effects[self::INPUT_PRODUCT_SALE_ELEMENT_ID_NAME];
        }
        if (isset($effects[self::INPUT_QUANTITY_NAME])) {
            $this->quantity = $effects[self::INPUT_QUANTITY_NAME];
        }

        return $this;
    }

    /**
     * Return effects generated by the coupon
     * A new product in the cart
     *
     * @return float The discount
     */
    public function exec()
    {
        // The CartItem price will be set to 0
        // So no need to return a discount
        $discount = 0;

        // Since the exec method will be called each time the cart checks its integrity
        //  We need to check if the free product has already been inserted in the Cart
        if (!$this->isAlreadyInCart($this->productSaleElementsId)) {

            /** @var ProductSaleElements $productToGive */
            $productToGive = $this->getFreeProduct();
            $this->addProductToCustomerCart($productToGive);
        }

        return $discount;
    }

    /**
     * Get I18n name
     *
     * @return string
     */
    public function getName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Add a free product to the customer cart', array(), 'coupon');
    }

    /**
     * Get I18n amount input name
     *
     * @return string
     */
    public function getInputName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Product Sale Element added to the cart', array(), 'coupon');
    }

    /**
     * Get I18n amount input name
     *
     * @return string
     */
    public function getInputQuantityName()
    {
        return $this->facade
            ->getTranslator()
            ->trans('Number of product added to the cart when entering this Coupon', array(), 'coupon');
    }

    /**
     * Get I18n tooltip
     *
     * @return string
     */
    public function getToolTip()
    {
        $toolTip = $this->facade
            ->getTranslator()
            ->trans(
                'This Coupon will give the associated product to the customer cart. The Coupon will make sure one order can get only one free product.',
                array(),
                'coupon'
            );

        return $toolTip;
    }

    /**
     * Use Thelia\Cart\CartTrait for searching current cart or create a new one
     * Then fill in a Cart Event
     *
     * @return \Thelia\Core\Event\Cart\CartEvent
     */
    protected function getCartEvent()
    {
        $cart = $this->facade->getCart();

        return new CartEvent($cart);
    }

    /**
     * Add a product to the Customer Cart
     * By generating CartEvent and dispatching it
     *
     * @param ProductSaleElements $productSaleElements Product Sale Elements to add
     *
     * @return $this
     */
    protected function addProductToCustomerCart(ProductSaleElements $productSaleElements)
    {
        $cartEvent = $this->getCartEvent();
        $cartEvent->setNewness(true);
        $cartEvent->setAppend(true);
        $cartEvent->setQuantity($this->quantity);
        $cartEvent->setProductSaleElementsId($this->productSaleElementsId);
        $cartEvent->setProduct($productSaleElements->getProductId());

        $this->facade->getDispatcher()->dispatch(TheliaEvents::CART_ADDITEM, $cartEvent);

        return $this;
    }

    /**
     * Get a product from its Product Sale Elements id
     *
     * @return ProductSaleElements
     */
    protected function getFreeProduct()
    {
        $productSaleElementsQuery = new ProductSaleElementsQuery();

        /** @var ProductSaleElements $productSaleElements */
        $productSaleElements = $productSaleElementsQuery->findOneById(
            $this->productSaleElementsId
        );

        return $productSaleElements;
    }

    /**
     * Check if the given Product Sale Elements id is already in the Cart
     *
     * @param int $productSaleElementsId Product Sale Elements id
     *
     * @return bool
     */
    protected function isAlreadyInCart($productSaleElementsId)
    {
        $return = false;
        $cart = $this->facade->getCart();
        $items = $cart->getCartItemsJoinProductSaleElements();

        /** @var  CartItem $cartItem */
        foreach ($items as $cartItem) {
            if ($cartItem->getProductSaleElementsId() == $productSaleElementsId) {
                $return = true;
                break;
            }
        }

        return $return;
    }

    /**
     * Get Product Sale Element id to give
     *
     * @return int
     */
    public function getProductSaleElementsId()
    {
        return $this->productSaleElementsId;
    }

    /**
     * Get quantity to give
     *
     * @return int
     */
    public function getQuantity()
    {
        return $this->quantity;
    }

    /**
     * Draw the input displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     *
     * @return string HTML string
     */
    public function drawBackOfficeInputs()
    {
        $labelProductSaleElementId = $this->getInputName();
        $labelQuantity = $this->getInputQuantityName();
        $value = $this->productSaleElementsId;

        $innerSelectHtml = $this->drawBackOfficePSESelect($value);

        $html = '
                <input type="hidden" name="thelia_coupon_creation[' . self::INPUT_AMOUNT_NAME . ']" value="0"/>
                <div class="form-group input-' . self::INPUT_PRODUCT_SALE_ELEMENT_ID_NAME . '">
                    <label for="' . self::INPUT_PRODUCT_SALE_ELEMENT_ID_NAME . '" class="control-label">' . $labelProductSaleElementId . '</label>
                    <select id="' . self::INPUT_PRODUCT_SALE_ELEMENT_ID_NAME . '" class="form-control" name="' . self::INPUT_EXTENDED__NAME . '[' . self::INPUT_PRODUCT_SALE_ELEMENT_ID_NAME . ']' . '" >' . $innerSelectHtml . '</select>
                </div>
                <div class="form-group input-' . self::INPUT_QUANTITY_NAME . '">
                    <label for="' . self::INPUT_QUANTITY_NAME . '" class="control-label">' . $labelQuantity . '</label>
                    <input id="' . self::INPUT_QUANTITY_NAME . '" class="form-control" name="' . self::INPUT_EXTENDED__NAME . '[' . self::INPUT_QUANTITY_NAME . ']' . '" type="text" value="' . $this->quantity . '"/>
                </div>
            ';

        return $html;
    }

    /**
     * Draw the select displayed in the BackOffice
     * allowing Admin to set its Coupon effect
     *
     * @return string HTML string
     *
     * @param int $value Already set Id
     *
     * @return string HTML select string
     */
    protected function drawBackOfficePSESelect($value)
    {
        $productSaleElementsQuery = ProductSaleElementsQuery::create();
        $productSaleElements = $productSaleElementsQuery->find();

        $innerSelectHtml = '';
        /** @var ProductSaleElements $productSaleElement */
        foreach ($productSaleElements as $productSaleElement) {
            $selected = '';
            if ($productSaleElement->getId() == $value) {
                $selected = ' selected="selected"';
            }
            $option = '
            <option value="' . $productSaleElement->getId() . '" ' . $selected . '>
                ' . $productSaleElement->getProduct()->getTitle() . ' (' . $productSaleElement->getRef() . ')
            </option>';

            $innerSelectHtml .= $option;
        }

        return $innerSelectHtml;
    }

}
