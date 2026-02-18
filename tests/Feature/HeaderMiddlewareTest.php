<?php

namespace Tests\Feature;

use Tests\TestCase;

class HeaderMiddlewareTest extends TestCase
{
    /**
     * Test que le middleware AddCustomHeaders ajoute bien les signatures.
     */
    public function test_custom_headers_are_present()
    {
        // On appelle une route simple (même le login ou l'index)
        $response = $this->getJson('/api/v1/books');

        // On vérifie que le header de signature est là
        $response->assertHeader('X-Code', 'X_Project_Dj_Fresh_2005');

        // On vérifie qu'un message de développeur est présent
        $this->assertNotEmpty($response->headers->get('X-Developer-Message'));

        // On vérifie que le message fait partie de ta liste
        $messages = [
            "Ha ! Enfin un dev back !",
            "Bienvenue dans les coulisses !",
            "Lookin' for something special ?",
            "Easter egg found: Check the X-code above !",
        ];
        $this->assertContains($response->headers->get('X-Developer-Message'), $messages);
    }
}
