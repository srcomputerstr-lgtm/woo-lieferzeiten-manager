<?php
/**
 * Optional JTL Connector plugin for WLM 1.43.2+ free-storage order SKU snapshots.
 * Install separately in <CONNECTOR_DIR>/plugins/WlmFreeStorage/Bootstrap.php.
 * This file is NOT loaded by the WordPress WLM plugin.
 */
namespace WlmFreeStorage;

use DI\Container;
use Jtl\Connector\Core\Definition\Action;
use Jtl\Connector\Core\Definition\Controller;
use Jtl\Connector\Core\Definition\Event;
use Jtl\Connector\Core\Event\CustomerOrderEvent;
use Jtl\Connector\Core\Model\CustomerOrderItem;
use Jtl\Connector\Core\Plugin\PluginInterface;
use Noodlehaus\ConfigInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class Bootstrap implements PluginInterface
{
    public function registerListener(ConfigInterface $config, Container $container, EventDispatcher $dispatcher): void
    {
        $dispatcher->addListener(
            Event::createEventName(Controller::CUSTOMER_ORDER, Action::PULL, Event::AFTER),
            array($this, 'onCustomerOrderAfterPull')
        );
    }

    public function onCustomerOrderAfterPull(CustomerOrderEvent $event): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $export = $event->getOrder();
        $endpoint = $export->getId()->getEndpoint();
        if (!is_string($endpoint) || !preg_match('/^[1-9][0-9]*$/D', $endpoint)) {
            return;
        }
        $order = wc_get_order((int) $endpoint);
        if (!$order instanceof \WC_Order) {
            return;
        }
        $selection = $order->get_meta('_wlm_storage_selection', true);
        $sku = $order->get_meta('_wlm_storage_sku', true);
        if (!is_string($selection) || '' === $selection || 'asap' === $selection || !self::validSku($sku)) {
            return;
        }
        $lines = $order->get_items('line_item');
        foreach ($export->getItems() as $item) {
            if (!$item instanceof CustomerOrderItem || CustomerOrderItem::TYPE_PRODUCT !== $item->getType()) {
                continue;
            }
            $id = $item->getId()->getEndpoint();
            if (!is_string($id) || !preg_match('/^[1-9][0-9]*$/D', $id) || !isset($lines[(int) $id])) {
                continue;
            }
            $line = $lines[(int) $id];
            if (!$line instanceof \WC_Order_Item_Product
                || '1' !== (string) $line->get_meta('_wlm_free_storage', true)
                || 0 !== (int) $line->get_product_id()
                || 0 !== (int) $line->get_variation_id()
                || $sku !== $line->get_meta('_wlm_storage_sku', true)
                || 1.0 !== (float) $line->get_quantity()
                || 0.0 !== (float) $line->get_subtotal()
                || 0.0 !== (float) $line->get_total()
                || '' !== $item->getProductId()->getEndpoint()) {
                continue;
            }
            // Modify the response model only. Never set productId, save an order,
            // read current options/session, create products or change other fields.
            $item->setSku($sku);
        }
    }

    private static function validSku($sku): bool
    {
        return is_string($sku) && preg_match('/^EINLAGER-[0-9]{3,8}$/D', $sku)
            && (int) substr($sku, 9) > 0
            && sprintf('EINLAGER-%03d', (int) substr($sku, 9)) === $sku;
    }
}
