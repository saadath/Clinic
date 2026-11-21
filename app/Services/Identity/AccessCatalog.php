<?php

namespace App\Services\Identity;

class AccessCatalog
{
    public const PERMISSIONS = ['users.manage', 'roles.manage', 'doctors.view', 'doctors.view-all', 'doctors.manage', 'assignments.manage', 'patients.view', 'patients.view-all', 'patients.create', 'patients.update', 'medical-records.view', 'medical-records.view-all', 'medical-records.manage', 'medical-records.manage-all', 'forms.view', 'forms.manage', 'forms.manage-all', 'forms.test'];

    /** @return array<string, array{label: string, permissions: list<string>}> */
    public static function roles(): array
    {
        $roles = [
            'super-admin' => ['label' => 'Super Admin', 'permissions' => self::PERMISSIONS],
            'clinic-administrator' => ['label' => 'Clinic Administrator', 'permissions' => self::PERMISSIONS],
            'doctor' => ['label' => 'Doctor', 'permissions' => ['doctors.view', 'patients.view', 'patients.view-all', 'patients.create', 'patients.update', 'medical-records.view', 'medical-records.manage', 'forms.view', 'forms.manage', 'forms.test']],
            'receptionist' => ['label' => 'Receptionist', 'permissions' => ['doctors.view', 'patients.view', 'patients.create', 'patients.update']],
            'general-receptionist' => ['label' => 'General Receptionist', 'permissions' => ['doctors.view', 'doctors.view-all', 'patients.view', 'patients.view-all', 'patients.create', 'patients.update']],
            'doctor-assistant' => ['label' => 'Doctor Assistant', 'permissions' => ['doctors.view', 'patients.view', 'patients.create', 'patients.update', 'medical-records.view', 'forms.view', 'forms.test']],
            'accountant' => ['label' => 'Accountant', 'permissions' => []],
            'blog-manager' => ['label' => 'Blog Manager', 'permissions' => []],
            'blog-editor' => ['label' => 'Blog Editor', 'permissions' => []],
            'blog-author' => ['label' => 'Blog Author', 'permissions' => []],
            'patient' => ['label' => 'Patient', 'permissions' => []],
        ];

        return $roles;
    }
}
