<?php

declare(strict_types=1);

namespace Shroud\Schema\Glossary;

use Shroud\Schema\DraftRule;

/**
 * Names the columns in French, for the schemas written in that language.
 *
 * Same order as the English glossary: secrets, technical columns, then personal data.
 */
final class French implements GlossaryInterface
{
    public function rules(): array
    {
        return [
            // Secrets and sensitive data: cleared.
            new DraftRule('/^(mot_de_passe|mdp|secret|jeton|cle|cles|empreinte)$|_(jeton|cle|cles|secret)$/', 'new Clear()'),
            new DraftRule('/(^|_)(rib|iban|bic|refcb|sexe|genre|religion)(_|$)/', 'new Clear()'),
            new DraftRule('/(^|_)(journal|historique)$/', 'new Clear()', review: true),
            // Dates to shift, before the dates to keep.
            new DraftRule('/naissance/', 'new ShiftedDate()'),
            // Technical columns: kept.
            new DraftRule('/^(statut|etat|langue|lang|rang|ordre|actif|active|visible|publie|public|supprime|maj|date|date_|version|devise|montant|prix|total|quantite|code_pays|format|mode)(_|$)/', 'new Keep()'),
            new DraftRule('/(^|_)(donnees|parametres|preferences|prefs|reglages|options|config|configuration|extra)$/', 'new Keep()', review: true),
            // Personal data: an exact name is sure, an affixed name is to review.
            new DraftRule('/^(courriel|mel)$/', 'new Email()'),
            new DraftRule('/(^|_)(courriel|mel)(_|$)/', 'new Email()', review: true),
            new DraftRule('/^prenom$/', 'new FirstName()'),
            new DraftRule('/^nom_famille$/', 'new LastName()'),
            new DraftRule('/^nom_site$/', 'new ShapedText()'),
            new DraftRule('/^(nom|nom_complet|auteur|signataire)$|_nom$/', 'new FullName()', review: true),
            new DraftRule('/^(identifiant|pseudo|pseudonyme)$/', 'new Login()', review: true),
            new DraftRule('/(^|_)(lien|site_web|adresse_site|page_web)(_|$)/', 'new Url(keepHost: false)', review: true),
            new DraftRule('/^(tel|telephone|portable|gsm|fax)(_?\d)?$|_(tel|telephone|portable|gsm|fax)$/', 'new ShapedDigits(keepDiallingCode: true)'),
            new DraftRule('/(^|_)(adresse|rue|voie)(_?\d)?$/', 'new Street()'),
            new DraftRule('/(^|_)(code_postal|codepostal|cp)$/', 'new PostalCode()'),
            new DraftRule('/(^|_)(ville|commune|localite)$/', 'new City()'),
            new DraftRule('/(^|_)(pays|region|departement|canton)$/', 'new Keep()', review: true),
            new DraftRule('/(^|_)(societe|organisme|entreprise|cabinet|employeur|organisation|institution)(_|$)/', 'new Organization()'),
            new DraftRule('/^(fonction|poste|profession|metier)$/', 'new ShapedText()'),
            new DraftRule('/^(civilite|titre_civilite)$/', 'new Keep()', review: true),
            new DraftRule('/^(bio|biographie|presentation|a_propos)$/', 'new ShapedText()'),
            new DraftRule('/(^|_)(tva|no_tva|numero_tva|siret|siren|no_tva_intra)(_|$)/', 'new ShapedText()'),
        ];
    }
}
