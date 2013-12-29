<?php
/*************************************************************************************/
/*                                                                                   */
/*      Thelia	                                                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : info@thelia.net                                                      */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      This program is free software; you can redistribute it and/or modify         */
/*      it under the terms of the GNU General Public License as published by         */
/*      the Free Software Foundation; either version 3 of the License                */
/*                                                                                   */
/*      This program is distributed in the hope that it will be useful,              */
/*      but WITHOUT ANY WARRANTY; without even the implied warranty of               */
/*      MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the                */
/*      GNU General Public License for more details.                                 */
/*                                                                                   */
/*      You should have received a copy of the GNU General Public License            */
/*	    along with this program. If not, see <http://www.gnu.org/licenses/>.         */
/*                                                                                   */
/*************************************************************************************/

namespace CouponGiveProduct\Action;

use CouponGiveProduct\Coupon\Type\GiveProduct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Action\BaseAction;
use Thelia\Core\Event\Cart\CartEvent;
use Thelia\Core\Event\Coupon\CouponCreateOrUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Coupon\FacadeInterface;
use Thelia\Coupon\Type\CouponInterface;
use Thelia\Model\CartItem;

/**
 * Created by JetBrains PhpStorm.
 * Date: 20/12/13
 * Time: 19:24 PM
 *
 * Allow to manage event when a CartItem is added to the Cart
 *
 * @package Coupon
 * @author  Guillaume MOREL <gmorel@openstudio.fr>
 *
 */
class CouponGiveProduct extends BaseAction implements EventSubscriberInterface
{

    /**
     * Set the current CartItem as free
     * If the ProductSaleElement is given
     * by one of the already entered Coupon
     *
     * @param CartEvent $event
     */
    public function setCartItemAsFree(CartEvent $event)
    {
        // Retrieve CartItem from the event
        /** @var CartItem $cartItem */
        $cartItem = $event->getCartItem();

        if ($cartItem->getPrice() > 0) {
            /** @var FacadeInterface $facade */
            $facade = $this->container->get('thelia.facade');
            // Retrieve all Coupon already entered and validated for the current Order
            $coupons = $facade->getCurrentCoupons();
            $giveProduct = new GiveProduct($facade);

            /** @var CouponInterface $coupon */
            foreach ($coupons as $coupon) {
                // If a GiveProduct Coupon type has been entered by the Customer
                if ($coupon->getServiceId() == $giveProduct->getServiceId()) {
                    // If the inserted ProductSaleElement is offered by a Coupon
                    /** @var GiveProduct $coupon */
                    if ($cartItem->getProductSaleElementsId() == $coupon->getProductSaleElementsId()) {
                        $cartItem->setPrice(0);
                        $cartItem->setPriceEndOfLife(0);
                        $cartItem->setPromoPrice(0);
                        $cartItem->setDiscount(0);
                        $cartItem->save();
                        // Feed the Event with the free CartItem
                        $event->setCartItem($cartItem);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 128)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 128 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * 127 or less means the action will be called after the default action
     * 129 or more means the action will be called before the default action
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            // CouponGiveProduct\Action\CouponGiveProduct::setCartItemAsFree()
            // will be called after
            // Thelia\Action\Cart::addItem()
            TheliaEvents::CART_ADDITEM => array('setCartItemAsFree', 127),
        );
    }
}