# Høringsportalen

We use [Webpack
Encore](http://symfony.com/doc/current/frontend.html#webpack-encore)
to handle frontend assets, see
http://symfony.com/doc/current/frontend.html#webpack-encore for
details.


JavaScript and CSS (actually SCSS) assets are put in `assets/js/` and
`assets/css/`, respectively, and built assets are put in `build/`.


## Building assets

First, install tools and requirements:

```sh
npm config set engine-strict true
npm install
```

Build for development:

```
./node_modules/.bin/encore dev --watch
```

Build for production:

```
./node_modules/.bin/encore production
```
