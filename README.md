# PageListCustomSort

This module enables the use of a custom sort setting for children, using multiple properties.

<img width="1680" alt="Screenshot 2024-09-13 at 15 40 17" src="https://github.com/user-attachments/assets/ef160506-48e5-4530-90f1-8addce20db1d">

Modules directory: [https://processwire.com/modules/page-list-custom-sort/](https://processwire.com/modules/page-list-custom-sort/)

Support forum: [https://processwire.com/talk/topic/30410-pagelist-custom-sort/](https://processwire.com/talk/topic/30410-pagelist-custom-sort/)

## About

This module is similar to [ProcessPageListMultipleSorting](https://processwire.com/modules/process-page-list-multiple-sorting/) by [David Karich](https://processwire.com/modules/author/david-karich/) but is closer to what could (should?) be in the core as it adds the custom sort setting in both the template’s “Family” tab and in the page’s “Children” tab (when applicable).

## Usage

Once a custom sort is set, it is applied in the page list but also when calling `$page->children()` or `$page->siblings()`.

You can also apply the custom sort when calling `$page->find("sort=_custom")` or `$pages->find("parent_id|has_parent=$id,sort=_custom")`. Unfortunately this won’t work the same way [`sort=sort`](https://processwire.com/docs/selectors/#sort) does if you only specify the template in your selector.
