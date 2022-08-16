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
      $sibling_components = $section->getComponentsForRegion($region);
    }
    else {
      $layout_id = '';
      $sibling_components = $layout->getRootComponents();
    }
    $count = count($sibling_components);

    $restrictions = \Drupal::moduleHandler()->invokeAll('layout_paragraphs_restrictions');

    // Filter to matching layouts.
    $restrictions = array_filter(
      $restrictions,
      function ($restrictions) use ($layout_id) {
        return (self::restrictByProperty($restrictions['layouts'] ?? NULL, $layout_id));
      }
    );

    // Filter to matching regions.
    $restrictions = array_filter(
      $restrictions,
      function ($restrictions) use ($region) {
        return (self::restrictByProperty($restrictions['regions'] ?? NULL, $region));
      }
    );

    if ($restrictions) {

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

      $event->setTypes($types);
    }

  }

  /**
   * Returns true if the restriction $property is undefined or contains $value.
   *
   * @param null|array $property
   *   The restriction property.
   * @param null|string $value
   *   The value to check.
   *
   * @return bool
   *   True if matches.
   */
  private static function restrictByProperty($property, $value) {
    return empty($property) ||
      (is_array($property) &&
        in_array($value, $property));
  }

  /**
   * Returns true if the restriction $property is greater than the $value.
   *
   * @param null|array $property
   *   The restriction property.
   * @param null|string $value
   *   The value to check.
   *
   * @return bool
   *   True if matches.
   */
  private static function restrictByCount($property, $value) {
    return empty($property) || $value < $property;
  }

}
