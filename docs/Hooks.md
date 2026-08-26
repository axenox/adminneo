# Hooks available for plugins

Plugins can override certain methods, thus replacing some original functionality. The intended purpose of these hooks (= things plugins can customize) is described below.

## Custom schema renderer

The schema route calls `Origin::printDatabaseSchema()` immediately after the shared page header. A plugin can print a replacement page body and return `true`; the route then returns to `admin/index.php`, which still prints the standard footer. Plugins that are not applicable return `null`. The default implementation returns `false`, causing `schema.inc.php` to render AdminNeo's native draggable schema.

This hook deliberately contains no diagram-library-specific behavior. For example, the Power UI integration registers its external `MermaidSchemaPlugin`, while standalone and compiled AdminNeo installations retain the native renderer unless they explicitly register another plugin. A renderer is responsible for safely escaping its output and loading any external assets through the normal head hooks.