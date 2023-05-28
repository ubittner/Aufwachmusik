<?php

/**
 * @project       Aufwachmusik/Aufwachmusik/helper
 * @file          control.php
 * @author        Ulrich Bittner
 * @copyright     2023 Ulrich Bittner
 * @license       https://creativecommons.org/licenses/by-nc-sa/4.0/ CC BY-NC-SA 4.0
 */

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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt.', 0);
        $this->SendDebug(__FUNCTION__, 'Status: ' . json_encode($State), 0);

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
            $devicePowerID = $this->ReadPropertyInteger('DevicePower');
            if ($devicePowerID <= 1 || @!IPS_ObjectExists($devicePowerID)) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, DevicePower (Aus/An) ist nicht vorhanden!', 0);
                return false;
            }

            $deviceVolumeID = $this->ReadPropertyInteger('DeviceVolume');
            if ($deviceVolumeID <= 1 || @!IPS_ObjectExists($deviceVolumeID)) {
                $this->SendDebug(__FUNCTION__, 'Abbruch, DeviceVolume (Lautstärke) ist nicht vorhanden!', 0);
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
            $this->WriteAttributeInteger('EndTime', $timestamp);

            //Set device volume
            @RequestAction($deviceVolumeID, 1);

            //Set device preset
            $devicePresetID = $this->ReadPropertyInteger('DevicePresets');
            if ($devicePresetID > 1 && @IPS_ObjectExists($devicePresetID)) {
                $preset = $this->GetValue('Presets');
                if ($preset > 1) {
                    @RequestAction($devicePresetID, $preset);
                }
            }

            //Power device on
            @RequestAction($devicePowerID, true);

            //Set next cycle
            $this->SetTimerInterval('IncreaseVolume', $this->CalculateNextCycle() * 1000);
        }

        return true;
    }

    /**
     * Decreases the volume of the device.
     *
     * @return void
     * @throws Exception
     */
    public function IncreaseVolume(): void
    {
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt.', 0);

        $devicePowerID = $this->ReadPropertyInteger('DevicePower');
        if ($devicePowerID <= 1 || @!IPS_ObjectExists($devicePowerID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, DevicePower (Aus/An) ist nicht vorhanden!', 0);
        }

        $deviceVolumeID = $this->ReadPropertyInteger('DeviceVolume');
        if ($deviceVolumeID <= 1 || @!IPS_ObjectExists($deviceVolumeID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, DevicePower (Lautstärke) ist nicht vorhanden!', 0);
        }

        $actualDeviceVolume = GetValue($deviceVolumeID);
        $targetVolume = $this->ReadAttributeInteger('TargetVolume');

        //Abort, if the device has been switched off by the user in the meantime
        if (!GetValue($devicePowerID) || $actualDeviceVolume == 0) {
            $this->ToggleWakeUpMusic(false);
            return;
        }

        //Abort if the current volume is lower or higher than the last cycle volume
        $cyclingVolume = $this->ReadAttributeInteger('CyclingVolume');
        if ($actualDeviceVolume > ($cyclingVolume + 1) || $actualDeviceVolume < $cyclingVolume) {
            $this->ToggleWakeUpMusic(false);
            return;
        }

        //Last cycle
        if ($cyclingVolume == $targetVolume) {
            $this->ToggleWakeUpMusic(false);
            return;
        }

        //Cycle
        if ($actualDeviceVolume >= 1 && $actualDeviceVolume < $targetVolume) {
            //Increase volume
            @RequestAction($deviceVolumeID, $actualDeviceVolume + 1);
            $this->WriteAttributeInteger('CyclingVolume', $actualDeviceVolume + 1);
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
        $this->SendDebug(__FUNCTION__, 'wird ausgeführt.', 0);
        $deviceVolumeID = $this->ReadPropertyInteger('DeviceVolume');
        if ($deviceVolumeID <= 1 || @!IPS_ObjectExists($deviceVolumeID)) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, DeviceVolume (Lautstärke) ist nicht vorhanden!', 0);
            return 0;
        }
        $actualDeviceVolume = GetValue($deviceVolumeID);
        //Abort cases
        if ($actualDeviceVolume == 0 || $actualDeviceVolume >= $this->ReadAttributeInteger('TargetVolume')) {
            $this->ToggleWakeUpMusic(false);
            return 0;
        }
        $dividend = $this->ReadAttributeInteger('EndTime') - time();
        $divisor = $this->ReadAttributeInteger('TargetVolume') - $actualDeviceVolume;
        //Check dividend and divisor
        if ($dividend <= 0 || $divisor <= 0) {
            $this->ToggleWakeUpMusic(false);
            return 0;
        }
        $remainingTime = intval(round($dividend / $divisor));
        $this->SendDebug(__FUNCTION__, 'Nächste Ausführung in: ' . $remainingTime, 0);
        return $remainingTime;
    }
}