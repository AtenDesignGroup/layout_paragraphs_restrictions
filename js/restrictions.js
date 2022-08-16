(($, Drupal, debounce, dragula) => {

  Drupal.behaviors.layoutParagraphsRestrictions = {
    attach: function attach(context, settings) {
      $('[data-lpb-id]')
        .once('lpb-restrictions')
        .each((i, e) => {
          const lpbId = e.getAttribute('data-lpb-id');
          if (
            typeof settings.lpBuilder.restrictions !== 'undefined' &&
            typeof settings.lpBuilder.restrictions[lpbId] !== 'undefined'
            ) {
            const allRestrictions = settings.lpBuilder.restrictions[lpbId];
            Drupal.registerLpbMoveError((settings, el, target, source, sibling) => {
              const type = el.getAttribute('data-type');
              const region = target.getAttribute('data-region') || '_root';
              const layout = $(target).closest('[data-layout]').attr('data-layout');

              let restrictions = allRestrictions.filter((restriction) => {
                const appliesToRegion = restriction.regions == undefined ||
                  (Array.isArray(restriction.regions) && restriction.regions.includes(region));
                const appliesToLayout = restriction.layouts == undefined ||
                  (Array.isArray(restriction.layouts) && restriction.layouts.includes(layout));
                return appliesToLayout && appliesToRegion;
              });

              if (restrictions.length) {
                const $children = $(target).children('.js-lpb-component');
                const count = $children.length;
                const existingTypes = $children
                  .toArray()
                  .map((i) => i.getAttribute('data-type'))
                  .filter((a, b, c) => c.indexOf(a) === b);

                if (existingTypes.length) {
                  restrictions = restrictions.filter(
                    (v) => existingTypes.reduce((_p, c) => {
                      const max = v.max || 10000;
                      return Object.keys(v.components).includes(c) && count < max;
                    }, true)
                  );
                }

                const allowedTypes = restrictions.reduce(
                  (p, c) => p.concat([...Object.keys(c.components)]), []
                ).filter(
                  (a, b, c) => c.indexOf(a) === b
                );

                if (!allowedTypes.includes(type)) {
                  return `${type} is not allowed in the ${region} region.`
                }
              }
            });
          }
        });
    }
  }

})(jQuery, Drupal, Drupal.debounce, dragula);
