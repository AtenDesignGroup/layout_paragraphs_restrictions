<?php

namespace Drupal\layout_paragraphs_restrictions\EventSubscriber;

use Drupal\Core\Messenger\Messenger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent;

/**
 * Provides Layout Paragraphs Restrictions.
 */
class LayoutParagraphsRestrictions implements EventSubscriberInterface {

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * Constructor.
   *
   * We use dependency injection get Messenger.
   *
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   */
  public function __construct(Messenger $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents() {
    return [
      LayoutParagraphsAllowedTypesEvent::EVENT_NAME => 'typeRestrictions',
    ];
  }

  /**
   * Restricts available types based on settings in layout.
   *
   * @param \Drupal\layout_paragraphs\Event\LayoutParagraphsAllowedTypesEvent $event
   *   The allowed types event.
   */
  public function typeRestrictions(LayoutParagraphsAllowedTypesEvent $event) {

    $parent_uuid = $event->getParentUuid();
    $types = $event->getTypes();
    $layout = $event->getLayout();
    $region = $event->getRegion() ?? '_root';
    if ($parent_uuid) {
      $parent_component = $layout->getComponentByUuid($parent_uuid);
      $section = $layout->getLayoutSection($parent_component->getEntity());
      $layout_id = $section->getLayoutId();
    }
    else {
      $layout_id = '';
    }

    $all_restrictions = \Drupal::moduleHandler()->invokeAll('layout_paragraphs_restrictions');

    // Filter restrictions to those that apply to this layout and region.
    $restrictions = array_filter(
      $all_restrictions,
      function ($restriction) use ($region, $layout_id) {
        $applies_to_layout = empty($restriction['layouts']) ||
          (is_array($restriction['layouts']) && in_array($layout_id, $restriction['layouts']));
        $applies_to_region = empty($restriction['regions']) ||
          (is_array($restriction['regions']) && in_array($region, $restriction['regions']));
        return $applies_to_layout && $applies_to_region;
      }
    );

    if ($restrictions) {

      if ($parent_uuid) {
        $parent_component = $layout->getComponentByUuid($parent_uuid);
        $parent_section = $layout->getLayoutSection($parent_component->getEntity());
        $sibling_components = $parent_section->getComponentsForRegion($region);
      }
      else {
        $sibling_components = $layout->getRootComponents();
      }

      // Build a list of existing component types for this layout/region.
      $count = count($sibling_components);
      $existing_types = array_reduce($sibling_components, function ($carry, $item) {
        $carry[$item->getEntity()->bundle()] = TRUE;
        return $carry;
      }, []);

      // Filter restrictions to those that match existing components types.
      if ($existing_types) {
        $restrictions = array_filter(
          $restrictions,
          function ($group) use ($existing_types, $count) {
            if ($group['restrictive']) {
              $a = array_keys($group['components']);
              $b = array_keys($existing_types);
              $max = $group['max'] ?? 10000;
              return (!array_diff($b, $a) && $count < $max);
            }
            else {
              return TRUE;
            }
          }
        );
      }

      // Build a list of allowed component types from the filtered restrictions.
      $allowed = array_reduce($restrictions, function ($carry, $item) {
        foreach (array_keys($item['components']) as $allowed_type) {
          $carry[$allowed_type] = TRUE;
        }
        return $carry;
      }, []);

      foreach (array_keys($types) as $key) {
        if (!$allowed[$key]) {
          unset($types[$key]);
        }
      }

      if (!count($types)) {
        $this->messenger->addMessage(t('There are no components available for this region.'));
      }

      $event->setTypes($types);
    }

  }

}
