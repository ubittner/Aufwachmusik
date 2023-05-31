<?php

/**
 * @project       Aufwachmusik/Aufwachmusik/helper
 * @file          control.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

/** @noinspection PhpVoidFunctionResultUsedInspection */

declare(strict_types=1);

trait Control
{
    /**
     * Toggles the wake-up music off or on.
     *
     * @param bool $State
     * false =  off,
     * true =   on
     *
     * @return bool
     * false =  an error occurred,
     * true =   successful
     *
     * @throws Exception
     */
    public function ToggleWakeUpMusic(bool $State): bool
    {
        $debugText = 'Aufwachmusik ausschalten';
        if ($State) {
            $debugText = 'Aufwachmusik einschalten';
        }
        $this->SendDebug(__FUNCTION__, $debugText, 0);
        //Off
        if (!$State) {
            $this->SetValue('WakeUpMusic', false);
            IPS_SetDisabled($this->GetIDForIdent('Volume'), false);
            IPS_SetDisabled($this->GetIDForIdent('Presets'), false);
            IPS_SetDisabled($this->GetIDForIdent('Duration'), false);
            $this->SetValue('ProcessFinished', '');
            @IPS_SetHidden($this->GetIDForIdent('ProcessFinished'), true);
            $this->WriteAttributeInteger('TargetVolume', 0);
            $this->WriteAttributeInteger('CyclingVolume', 0);
            $this->WriteAttributeInteger('EndTime', 0);
            $this->SetTimerInterval('IncreaseVolume', 0);
        } //On
        else {
            if (!$this->CheckDevicePowerID()) {
                return false;
            }
            if (!$this->CheckDeviceVolumeID()) {
                return false;
            }
            //Check if device is already powered on
            if (GetValue($this->ReadPropertyInteger('DevicePower'))) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, Gerät ist bereits eingeschaltet!', 0);
                return false;
            }
            //Timestamp
            $timestamp = time() + $this->GetValue('Duration') * 60;
            //Set values
            $this->SetValue('WakeUpMusic', true);
            IPS_SetDisabled($this->GetIDForIdent('Volume'), true);
            IPS_SetDisabled($this->GetIDForIdent('Presets'), true);
            IPS_SetDisabled($this->GetIDForIdent('Duration'), true);
            $this->SetValue('ProcessFinished', date('d.m.Y, H:i:s', $timestamp));
            @IPS_SetHidden($this->GetIDForIdent('ProcessFinished'), false);
            //Set attributes
            $this->WriteAttributeInteger('TargetVolume', $this->GetValue('Volume'));
            $this->WriteAttributeInteger('CyclingVolume', 1);
            $this->SendDebug(__FUNCTION__, 'Lautstärke: 1', 0);
            $this->WriteAttributeInteger('EndTime', $timestamp);
            //Set device volume
            $setDeviceVolume = @RequestAction($this->ReadPropertyInteger('DeviceVolume'), 1);
            //Try again
            if (!$setDeviceVolume) {
                @RequestAction($this->ReadPropertyInteger('DeviceVolume'), 1);
            }
            //Set device preset
            if ($this->CheckDevicePresetsID()) {
                $devicePresetID = $this->ReadPropertyInteger('DevicePresets');
                $preset = $this->GetValue('Presets');
                if ($preset >= 1) {
                    $setDevicePreset = @RequestAction($devicePresetID, $preset);
                    $this->SendDebug(__FUNCTION__, 'Preset: ' . $preset, 0);
                    //Try again
                    if (!$setDevicePreset) {
                        @RequestAction($devicePresetID, $preset);
                    }
                }
            }
            //Power device on
            $this->SendDebug(__FUNCTION__, 'Gerät einschalten', 0);
            $powerOnDevice = @RequestAction($this->ReadPropertyInteger('DevicePower'), true);
            //Try again
            if (!$powerOnDevice) {
                @RequestAction($this->ReadPropertyInteger('DevicePower'), true);
            }
            //Set next cycle
            $this->SetTimerInterval('IncreaseVolume', $this->CalculateNextCycle() * 1000);
        }
        return true;
    }

    /**
     * Increases the volume of the device.
     *
     * @return void
     * @throws Exception
     */
    public function IncreaseVolume(): void
    {
        if (!$this->CheckDevicePowerID()) {
            return;
        }
        if (!$this->CheckDeviceVolumeID()) {
            return;
        }
        $cyclingVolume = $this->ReadAttributeInteger('CyclingVolume');
        $targetVolume = $this->ReadAttributeInteger('TargetVolume');
        //Last cycle
        if ($cyclingVolume == $targetVolume) {
            $this->ToggleWakeUpMusic(false);
            return;
        }
        //Cycle
        $actualDeviceVolume = GetValue($this->ReadPropertyInteger('DeviceVolume'));
        if ($actualDeviceVolume >= 1 && $actualDeviceVolume < $targetVolume) {
            //Increase volume
            $this->WriteAttributeInteger('CyclingVolume', $cyclingVolume + 1);
            $this->SendDebug(__FUNCTION__, 'Lautstärke: ' . ($cyclingVolume + 1), 0);
            $setDeviceVolume = @RequestAction($this->ReadPropertyInteger('DeviceVolume'), $cyclingVolume + 1);
            //Try again
            if (!$setDeviceVolume) {
                @RequestAction($this->ReadPropertyInteger('DeviceVolume'), $cyclingVolume + 1);
            }
            //Set next cycle
            $this->SetTimerInterval('IncreaseVolume', $this->CalculateNextCycle() * 1000);
        }
    }

    #################### Private

    /**
     * Calculates the next cycle.
     *
     * @return int
     * @throws Exception
     */
    private function CalculateNextCycle(): int
    {
        if (!$this->CheckDeviceVolumeID()) {
            return 0;
        }
        $dividend = $this->ReadAttributeInteger('EndTime') - time();
        $divisor = $this->ReadAttributeInteger('TargetVolume') - GetValue($this->ReadPropertyInteger('DeviceVolume'));
        //Check dividend and divisor
        if ($dividend <= 0 || $divisor <= 0) {
            $this->ToggleWakeUpMusic(false);
            return 0;
        }
        return intval(round($dividend / $divisor));
    }

    /**
     * Checks for an existing device power id.
     *
     * @return bool
     * false =  doesn't exist,
     * true =   exists
     *
     * @throws Exception
     */
    private function CheckDevicePowerID(): bool
    {
        $devicePowerID = $this->ReadPropertyInteger('DevicePower');
        if ($devicePowerID <= 1 || @!IPS_ObjectExists($devicePowerID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, Power (Aus/An) ist nicht vorhanden!', 0);
            return false;
        }
        return true;
    }

    /**
     * Checks for an existing device volume id.
     *
     * @return bool
     * false =  doesn't exist,
     * true =   exists
     *
     * @throws Exception
     */
    private function CheckDeviceVolumeID(): bool
    {
        $deviceVolumeID = $this->ReadPropertyInteger('DeviceVolume');
        if ($deviceVolumeID <= 1 || @!IPS_ObjectExists($deviceVolumeID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, Lautstärke ist nicht vorhanden!', 0);
            return false;
        }
        return true;
    }

    /**
     * Checks for an existing device presets id.
     *
     * @return bool
     * false =  doesn't exist,
     * true =   exists
     *
     * @throws Exception
     */
    private function CheckDevicePresetsID(): bool
    {
        $devicePresetsID = $this->ReadPropertyInteger('DevicePresets');
        if ($devicePresetsID <= 1 || @!IPS_ObjectExists($devicePresetsID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, Presets ist nicht vorhanden!', 0);
            return false;
        }
        return true;
    }
}