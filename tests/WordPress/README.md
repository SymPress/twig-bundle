# Real WordPress integration

The QA workflow runs this isolated DDEV fixture in its dedicated WordPress
integration job. It installs the fixture's Composer dependencies and runs native
WordPress checks. The library has no root JavaScript application or Playwright
suite; Theme browser coverage lives in theme-starter.

```sh
ddev start
ddev exec bash tests/WordPress/setup.sh
ddev exec bash tests/WordPress/run.sh
```

The fixture installs WordPress, the source bundle through a Composer path
repository, a registered parent theme and an unregistered child. It checks lazy
content and native loop restoration (including nested loops, early exit and
exceptions), block rendering, PHP-template interception and the actual admin
custom-template list. Credentials are public disposable values; never deploy it.
