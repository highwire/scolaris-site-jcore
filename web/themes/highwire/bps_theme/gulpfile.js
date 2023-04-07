const gulp = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const cleanCSS = require('gulp-clean-css');
const sassGlob = require('gulp-sass-glob');
const uglify = require('gulp-uglify');
const terser = require('gulp-terser');
const sourceMaps = require('gulp-sourcemaps');
const concat = require('gulp-concat');
const browserSync = require('browser-sync').create();
const { exec } = require('child_process');

// Configure Path for Source, Patternlab and Drupal SCSS, Js, Fonts and Images, Twig
const scss = {
    src: ['source/default/css/**/*.scss'],
    pl_dest: ['dist/app-pl/assets/css'],
    drupal_dest: ['dist/app-drupal/assets/css']
}
const pluginJs = {
    src: ['source/default/js/plugin/*.js'],
    pl_dest: ['dist/app-pl/assets/js'],
    drupal_dest: ['dist/app-drupal/assets/js']
}
const customJs = {
    src: ['source/default/js/custom/*.js'],
    pl_dest: ['dist/app-pl/assets/js/custom'],
    drupal_dest: ['dist/app-drupal/assets/js/custom']
}
const fonts = {
    src: ['source/default/fonts/*'],
    pl_dest: ['dist/app-pl/assets/fonts'],
    drupal_dest: ['dist/app-drupal/assets/fonts']
}
const images = {
    src: ['source/default/images/*'],
    pl_dest: ['dist/app-pl/assets/images'],
    drupal_dest: ['dist/app-drupal/assets/images']
}

gulp.task('browser:sync', function () {
    browserSync.init({
        open: false,
        injectChanges: true,
        // proxy: {
        //     target: 'http://localhost:8080'
        // }
    });
    return gulp.watch;
});

gulp.task('build:sass', function () {
  return gulp
    .src(scss.src)
    .pipe(sassGlob())
    .pipe(sass({}).on('error', sass.logError))
    .pipe(cleanCSS())
    .pipe(gulp.dest(scss.pl_dest))
    .pipe(gulp.dest(scss.drupal_dest))
    .pipe(browserSync.stream());
});

// Plugin Compile
gulp.task('build:pluginjs', function () {
    // change to your source directory
    return gulp.src(pluginJs.src)
        .pipe(sourceMaps.init())
        .pipe(uglify())
        .pipe(concat('plugin.js'))
        .pipe(sourceMaps.write())
        // change to your final/public directory
        .pipe(gulp.dest(pluginJs.pl_dest))
        .pipe(gulp.dest(pluginJs.drupal_dest));
});

// Custom JS Compile
gulp.task('build:customjs', function () {
    // change to your source directory
    return gulp.src(customJs.src)
        .pipe(terser())
        // change to your final/public directory
        .pipe(gulp.dest(customJs.pl_dest))
        .pipe(gulp.dest(customJs.drupal_dest));
});

// Fonts Compile
gulp.task('build:fonts', function () {
    // change to your source directory
    return gulp.src(fonts.src)
        // change to your final/public directory
        .pipe(gulp.dest(fonts.pl_dest))
        .pipe(gulp.dest(fonts.drupal_dest));
});

// Image Compile
gulp.task('build:images', function () {
    // change to your source directory
    return gulp.src(images.src)        
        // change to your final/public directory
        .pipe(gulp.dest(images.pl_dest))
        .pipe(gulp.dest(images.drupal_dest));
});


gulp.task('watch', function () {
    gulp.watch(scss.src, gulp.parallel('build:sass'));
    gulp.watch(pluginJs.src, gulp.parallel('build:pluginjs'));
    gulp.watch(customJs.src, gulp.parallel('build:customjs'));
    gulp.watch(fonts.src, gulp.parallel('build:fonts'));
    gulp.watch(images.src, gulp.parallel('build:images'));
    gulp.watch(["*.twig", "*.json", "*.yml", "*.html"]);
});

gulp.task('default', gulp.series('build:sass', 'build:pluginjs', 'build:customjs', 'build:fonts', 'build:images'));
