<?php

/**
 * @project       Aufwachmusik/Aufwachmusik/helper
 * @file          presets.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

declare(strict_types=1);

trait Presets
{
    /**
     * Updates the presets profile.
     *
     * @return void
     * @throws Exception
     */
    public function UpdatePresetsProfile(): void
    {
        if (!$this->CheckDevicePresetsID()) {
            return;
        }

        $variableInfo = IPS_GetVariable($this->ReadPropertyInteger('DevicePresets'));
        $profileName = $variableInfo['VariableProfile'];
        echo $profileName;
        if ($profileName == '') {
            $profileName = $variableInfo['VariableCustomProfile'];
        }
        $variableProfile = IPS_GetVariableProfile($profileName);
        foreach ($variableProfile['Associations'] as $association) {
            if ($association['Value'] >= 1) {
                IPS_SetVariableProfileAssociation(self::MODULE_PREFIX . '.' . $this->InstanceID . '.Presets', $association['Value'], $association['Name'], $association['Icon'], $association['Color']);
            }
        }
    }
}