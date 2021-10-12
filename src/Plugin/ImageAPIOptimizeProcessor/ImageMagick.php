<?php

namespace Drupal\imageapi_optimize_imagemagick\Plugin\ImageAPIOptimizeProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\imageapi_optimize_binaries\ImageAPIOptimizeProcessorBinaryBase;

/**
 * Uses the ImageMagick binary to optimize images.
 *
 * @ImageAPIOptimizeProcessor(
 *   id = "convert",
 *   label = @Translation("ImageMagick"),
 *   description = @Translation("Uses the ImageMagick binary to optimize images.")
 * )
 */
class ImageMagick extends ImageAPIOptimizeProcessorBinaryBase {

  /**
   * {@inheritdoc}
   */
  protected function executableName() {
    return 'convert';
  }

  public function applyToImage($imageUri) {
    if ($cmd = $this->getFullPathToBinary()) {
      $dst = $this->sanitizeFilename($imageUri);

      $options = array(
        $dst,
        '-quiet',
        '-strip',
      );

      if ($this->configuration['density']) {
        $options[] = '-density ' . escapeshellarg($this->configuration['density']);
      }

      if ($this->configuration['colorspace']) {
        $options[] = '-colorspace ' . escapeshellarg($this->configuration['colorspace']);
      }

      if ($this->configuration['exec']) {
        $options[] = $this->configuration['exec'];
      }

      if (is_numeric($this->configuration['quality'])) {
        $options[] = '-quality ' . escapeshellarg($this->configuration['quality']);
      }

      $webp = true;
      if ($this->configuration['webp_enable']) {
        if (is_numeric($this->configuration['webp_quality'])) {
          $webpOptions = $options;
          $webpOptions[] = '-quality ' . escapeshellarg($this->configuration['webp_quality']);
        }
        $webp = $this->execShellCommand($cmd, $webpOptions, [$dst . '.webp']);
      }

      $avif = true;
      if ($this->configuration['avif_enable']) {
        if (is_numeric($this->configuration['avif_quality'])) {
          $avifOptions = $options;
          $avifOptions[] = '-quality ' . escapeshellarg($this->configuration['avif_quality']);
        }
        $avif = $this->execShellCommand($cmd, $avifOptions, [$dst . '.avif']);
      }

      $orginal = $this->execShellCommand($cmd, $options, [$dst]);

      return $webp && $avif && $orginal;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'quality' => 70,
      'exec' => '-sampling-factor 4:2:0',
      'density' => 72,
      'colorspace' => 'sRGB',
      'webp_enable' => TRUE,
      'webp_quality' => 60,
      'avif_enable' => TRUE,
      'avif_quality' => 40,
    ];
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['quality'] = array(
      '#title' => $this->t('Quality'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 100,
      '#description' => $this->t('Optionally enter a JPEG quality setting to use, 0 - 100. WARNING: LOSSY'),
      '#default_value' => $this->configuration['quality'],
    );

    $form['exec'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Arguments'),
      '#default_value' => $this->configuration['exec'],
      '#required' => FALSE,
    ];

    $form['density'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Change image resolution to 72 ppi'),
      '#default_value' => $this->configuration['density'],
      '#return_value' => 72,
      '#description' => $this->t("Resamples the image <a href=':help-url'>density</a> to a resolution of 72 pixels per inch, the default for web images. Does not affect the pixel size or quality.", [
        ':help-url' => 'http://www.imagemagick.org/script/command-line-options.php#density',
      ]),
    ];

    $form['colorspace'] = [
      '#type' => 'select',
      '#title' => $this->t('Convert colorspace'),
      '#default_value' => $this->configuration['colorspace'],
      '#options' => [
        'RGB' => $this->t('RGB'),
        'sRGB' => $this->t('sRGB'),
        'GRAY' => $this->t('Gray'),
      ],
      '#empty_value' => 0,
      '#empty_option' => $this->t('- Original -'),
      '#description' => $this->t("Converts processed images to the specified <a href=':help-url'>colorspace</a>. The color profile option overrides this setting.", [
        ':help-url' => 'http://www.imagemagick.org/script/command-line-options.php#colorspace',
      ]),
    ];

    $form['webp'] = [
      '#type' => 'details',
      '#title' => $this->t('WebP'),
    ];

    $form['webp']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('enable'),
      '#default_value' => $this->configuration['webp_enable'],
    ];

    $form['webp']['quality'] = array(
      '#title' => $this->t('Quality'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 100,
      '#description' => $this->t('Optionally enter a WebP quality setting to use, 0 - 100. WARNING: LOSSY'),
      '#default_value' => $this->configuration['webp_quality'],
    );

    $form['avif'] = [
      '#type' => 'details',
      '#title' => $this->t('Avif'),
    ];

    $form['avif']['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('enable'),
      '#default_value' => $this->configuration['avif_enable'],
    ];

    $form['avif']['quality'] = array(
      '#title' => $this->t('Quality'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 100,
      '#description' => $this->t('Optionally enter a Avif quality setting to use, 0 - 100. WARNING: LOSSY'),
      '#default_value' => $this->configuration['avif_quality'],
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // @todo make form config.
    $this->configuration['quality'] = $form_state->getValue('quality');
    $this->configuration['exec'] = $form_state->getValue('exec');
    $this->configuration['density'] = $form_state->getValue('density');
    $this->configuration['colorspace'] = $form_state->getValue('colorspace');
    $this->configuration['webp_enable'] = $form_state->getValue('webp')['enable'];
    $this->configuration['webp_quality'] = $form_state->getValue('webp')['quality'];
    $this->configuration['avif_enable'] = $form_state->getValue('avif')['enable'];
    $this->configuration['avif_quality'] = $form_state->getValue('avif')['quality'];
  }
}
