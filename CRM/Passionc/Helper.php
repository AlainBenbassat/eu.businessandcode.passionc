<?php

class CRM_Passionc_Helper {
  public static function analyze($id) {
    $msg = "Analyse contact id = $id<br>";

    try {
      // get contact from the temp table
      $tempContact = self::getContactFromTemp($id);
      if ($tempContact === FALSE) {
        throw new Exception("Erreur: contact non trouvé dans le fichier Excel", 1);
      }

      // check if the contact was processed
      if ($tempContact->Avancement != 'OK') {
        throw new Exception("Erreur: avancement <> OK", 1);
      }

      // make sure we have a first name and/or last name
      if (!$tempContact->Prénom && !$tempContact->Nom) {
        throw new Exception("Erreur: ni nom ni prénom", 1);
      }

      // check if we have an organization
      if ($tempContact->Raison_sociale) {
        // see if the org exists
        $civiOrg =  self::getOrganization($tempContact->Raison_sociale, $tempContact->Pro_Rue, $tempContact->Pro_Complément_1, $tempContact->Pro_Complément_2, $tempContact->Pro_Code_Postal, $tempContact->Pro_Ville, $tempContact->Pro_CEDEX);
        if ($civiOrg === FALSE) {
          // create the organization
          $msg .= 'Raison sociale ' . $tempContact->Raison_sociale . ' non trouvée > création';
          $civiOrg =  self::createOrganization($tempContact->Raison_sociale, $tempContact->Pro_Rue, $tempContact->Pro_Complément_1, $tempContact->Pro_Complément_2, $tempContact->Pro_Code_Postal, $tempContact->Pro_Ville, $tempContact->Pro_CEDEX);
        }

        // create the employer relationship exists
        $msg .= 'Création de la relation employeur';
        civicrm_api3('Contact', 'create', [
          'id' => $tempContact->Identifiant,
          'employer_id' => $civiOrg['id'],
        ]);

      }
      else {
        $msg .= 'Pas de raison sociale.<br>';
      }

      // remove the professional address
      $msg .= 'Suppression de l\'adresse pro';
      $sql = "delete from civicrm_address where contact_id = " . $tempContact->Identifiant . " and location_type_id  = 3";
      CRM_Core_DAO::executeQuery($sql);

      // update home address
      if ($tempContact->Perso_Rue || $tempContact->Perso_Code_Postal) {
        $msg .= 'Mise à jour adresse perso';
        self::updateHomeAddress($tempContact->Identifiant, $tempContact->Perso_Rue, $tempContact->Perso_Complément_1, $tempContact->Perso_Complément_2, $tempContact->Perso_Code_Postal, $tempContact->Perso_Ville, $tempContact->Perso_CEDEX);
      }
    }
    catch (Exception $e) {
      $msg .= $e->getMessage() . '<br>';
    }

    return $msg;
  }

  public static function getContactFromTemp($id) {
    $sql = "select * from tmp_pro_perso where Identifiant = $id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    if ($dao->fetch()) {
      return $dao;
    }
    else {
      return FALSE;
    }
  }

  public static function getOrganization($name, $street_address, $supplemental_address_1, $supplemental_address_2, $postal_code, $city, $postal_code_suffix) {
    $params = [
      'organization_name' => $name,
      'contact_type' => 'Organization',
      'sequential' => 1,
    ];
    $c = civicrm_api3('Contact', 'get', $params);
    if ($c['count'] == 0) {
      return FALSE;
    }
    else {
      foreach ($c['values'] as $contact) {
        // get the address
        $params = [
          'sequential' => 1,
        ];
        if ($street_address) {
          $params['street_address'] = $street_address;
        }
        if ($postal_code) {
          $params['postal_code'] = $postal_code;
        }
        if ($city) {
          $params['city'] = $city;
        }
        $address = civicrm_api3('Address', 'get', $params);
        if ($address['count'] > 0) {
          // found contact with that address
          return $contact;
        }
      }
    }

    return FALSE;
  }

  public static function createOrganization($name, $street_address, $supplemental_address_1, $supplemental_address_2, $postal_code, $city, $postal_code_suffix) {
    // create the organization
    $params = [
      'organization_name' => $name,
      'contact_type' => 'Organization',
    ];
    $org = civicrm_api3('Contact', 'create', $params);

    // create the address
    $params = [
      'contact_id' => $org['id'],
      'country_id' => 1076,
    ];
    if ($street_address) {
      $params['street_address'] = $street_address;
    }
    if ($supplemental_address_1) {
      $params['supplemental_address_1'] = $supplemental_address_1;
    }
    if ($supplemental_address_2) {
      $params['supplemental_address_2'] = $supplemental_address_2;
    }
    if ($postal_code) {
      $params['postal_code'] = $postal_code;
    }
    if ($city) {
      $params['city'] = $city;
    }
    if ($postal_code_suffix) {
      $params['postal_code_suffix'] = $postal_code_suffix;
    }

    civicrm_api3('Address', 'create', $params);

    return $org;
  }

  public static function updateHomeAddress($contact_id, $street_address, $supplemental_address_1, $supplemental_address_2, $postal_code, $city, $postal_code_suffix) {
    $params = [];

    try {
      // get the home address
      $addr = civicrm_api3('Address', 'getsingle', [
        'contact_id' => $contact_id,
        'location_type_id' => 4,
      ]);

      $params = [
        'id' => $addr['id'],
      ];
    }
    catch (Exception $e) {
      // does not exist
    }

    $params['contact_id'] = $contact_id;
    $params['location_type_id'] = 4;
    $params['country_id'] = 1076;

    if ($street_address) {
      $params['street_address'] = $street_address;
    }
    else {
      $params['street_address'] = '';
    }

    if ($supplemental_address_1) {
      $params['supplemental_address_1'] = $supplemental_address_1;
    }
    else {
      $params['supplemental_address_1'] = '';
    }

    if ($supplemental_address_2) {
      $params['supplemental_address_2'] = $supplemental_address_2;
    }
    else {
      $params['supplemental_address_2'] = '';
    }

    if ($postal_code) {
      $params['postal_code'] = $postal_code;
    }
    else {
      $params['postal_code'] = '';
    }

    if ($city) {
      $params['city'] = $city;
    }
    else {
      $params['city'] = '';
    }

    if ($postal_code_suffix) {
      $params['postal_code_suffix'] = $postal_code_suffix;
    }
    else {
      $params['postal_code_suffix'] = '';
    }

    // create/update the home address
    civicrm_api3('Address', 'create', $params);
  }
}
