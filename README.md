# Draw Framework Extra Bundle

This bundle is used to integrate draw/* component in Symfony.

Everything is configurable via the **draw_framework_extra** namespace.

All sections are disabled by default, even if the corresponding draw component is available.

You need to enable each section manually base on the fact they need configuration, or they will
create useless side effect (load, database structure, etc.) if they are not needed.

- [Application](./doc/APPLICATION.md)
- [Open Api](./doc/OPEN_API.md)
