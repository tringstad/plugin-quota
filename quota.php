<?php

final class quota extends rcube_plugin
{
    const ONE_KB = 1;
    const ONE_MB = 1024;
    const ONE_GB = 1048576;
    const ONE_TB = 1073741824;
    const ONE_PB = INF;

    /**
     * @var string
     */
    public $task = 'mail|settings';

    /**
     * The loaded configuration.
     *
     * @var array
     */
    private $config = [];

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        $this->loadPluginConfig();

        $this->add_texts('localization/', true);
        $this->add_hook('quota', [$this, 'quotaMessage']);
        $this->add_hook('settings_actions', [$this, 'settingsActions']);

        $this->register_action('plugin.' . __CLASS__, [$this, 'quotaInit']);

        $this->include_script('js/Chart-2.7.3.min.js');
        $this->include_script('js/draw.js');
    }

    public function settingsActions(array $args)
    {
        $args['actions'][] = [
            'action' => 'plugin.' . __CLASS__,
            'class' => 'quota',
            'label' => 'quota_plugin_title',
            'domain' => 'quota',
        ];

        return $args;
    }

    public function quotaInit()
    {
        $rc = rcmail::get_instance();

        $this->register_handler('plugin.body', [$this, 'quotaForm']);

        $rc->output->set_pagetitle($this->gettext('quota_plugin_title'));
        $rc->output->send('plugin');
    }

    public function quotaMessage(array $args)
    {
        $rc = rcmail::get_instance();

        $thresholds = [
            99 => 'error',
            90 => 'warning',
        ];

        krsort($thresholds);

        foreach ($thresholds as $percent => $level) {
            if ($args['percent'] >= $percent) {
                $rc->output->show_message($this->gettext("quota_meet_{$percent}"), $level);
                break;
            }
        }
    }

    public function quotaForm()
    {
        $rc = rcmail::get_instance();

        $quota = $rc->get_storage()->get_quota();

        if (isset($quota['total'])) {
            $quotaUsedKb = $quota['used'];
            $quotaFreeKb = $quota['total'] - $quota['used'];
        } else {
            $quotaUsedKb = 0;
            $quotaFreeKb = static::ONE_PB;
        }

        $quotaTotalKb = $quotaUsedKb + $quotaFreeKb;
        $quotaUsedHumanized = $this->humanizeKbQuota($quotaUsedKb);
        $quotaFreeHumanized = $this->humanizeKbQuota($quotaFreeKb);
        $quotaTotalHumanized = $this->humanizeKbQuota($quotaTotalKb);

        if (isset($quota['total'])) {
            $quotaText = sprintf(
                '%.2f%% ( %s of %s )',
                $quota['percent'],
                $quotaUsedHumanized,
                $quotaTotalHumanized
            );
        } else {
            $quotaText = $this->gettext('unknown');
        }

        $out = (
            html::div(
                ['class' => 'box'],
                html::div(
                    ['id' => 'prefs-title', 'class' => 'boxtitle'],
                    $this->gettext('quota_plugin_title')
                ) .
                html::div(
                    ['class' => 'boxcontent'],
                    // debug information
                    (
                        $this->config['debug'] ?
                            html::p(
                                ['id' => 'quotaPluginDebugInfo'],
                                (
                                    'dump $quota = ' . print_r($quota, true)
                                )
                            ) : ''
                    ) .
                    // text reprecentation
                    (
                        $this->config['enable_text_presentation'] ?
                            html::p(
                                null,
                                $this->gettext('space_used') . ': ' . $quotaText
                            ) : ''
                    ) .
                    // chart reprecentation
                    (
                        $this->config['enable_chart_presentation']
                            ? '<canvas id="chartContainer" style="height: 370px; width: 100%; max-width: 600px;"></canvas>'
                            : ''
                    ) .
                    // admin contact
                    (
                        $this->config['show_admin_contact'] ?
                            html::p(
                                null,
                                sprintf($this->gettext('problem_please_contact'), $this->config['admin_contact'])
                            ) : ''
                    )
                )
            )
        );

        $jsVars = [
            'charTitle' => $this->gettext('chart_title'),
            'labelUsedSpace' => $this->gettext('space_used'),
            'labelFreeSpace' => $this->gettext('space_free'),
            'quotaUsedKb' => $quotaUsedKb,
            'quotaFreeKb' => $quotaFreeKb,
            'quotaTotalKb' => $quotaTotalKb,
            'quotaUsedHumanized' => $quotaUsedHumanized,
            'quotaFreeHumanized' => $quotaFreeHumanized,
            'quotaTotalHumanized' => $quotaTotalHumanized,
        ];

        $out .= $this->config['enable_chart_presentation'] ?
            '<script>
                var plugin_quota_chart_vars = ' . json_encode($jsVars, JSON_UNESCAPED_UNICODE) . ';

                drawDiskQuota();
            </script>' : '';

        return $out;
    }

    private function humanizeKbQuota($quota, $round = 2)
    {
        $quota = (float) $quota;

        $units = [
            'PB' => static::ONE_PB,
            'TB' => static::ONE_TB,
            'GB' => static::ONE_GB,
            'MB' => static::ONE_MB,
            'KB' => static::ONE_KB,
        ];

        $partition = [static::ONE_KB, 'KB'];
        foreach ($units as $unit => $size) {
            if ($quota >= $size) {
                $partition = [$size, $unit];
                break;
            }
        }

        return round($quota / $partition[0], $round) . " {$partition[1]}";
    }

    /**
     * Load plugin configuration.
     *
     * @return self
     */
    private function loadPluginConfig()
    {
        $rc = rcmail::get_instance();

        $userPerf = $this->load_config('config.inc.php')
            ? $rc->config->all()
            : [];

        $this->load_config('config.inc.php.dist');
        $rc->config->merge($userPerf);

        $this->config = $rc->config->all();

        return $this;
    }
}
