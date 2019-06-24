<?php

namespace MoloniPrint\Jobs;

use MoloniPrint\Job;

class Config extends Common
{
    /**
     * Document constructor.
     * @param Job $job
     */
    public function __construct(Job &$job)
    {
        parent::__construct($job);
    }

    public function create()
    {
        $this->builder->textFont("A");
        $this->builder->textDouble(true, true);
        $this->builder->text("Impressão de teste");

        $this->linebreak();
        $this->linebreak();

        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->text("Teste de imagem");

        $this->linebreak();
        $this->linebreak();

        $this->builder->textStyle();
        $this->builder->text("Confirme se a imagem aparece correctamente");

        $this->linebreak();
        $this->linebreak();

        $this->builder->image('https://www.moloni.pt/_imagens/_tmpl/ac_logo_topo_default_01.png');

        $this->linebreak();
        $this->linebreak();

        $this->builder->textDouble();
        $this->builder->textStyle(false, false, true);
        $this->builder->text("Testes de texto");

        $this->linebreak();
        $this->linebreak();

        $this->builder->textDouble();
        $this->builder->textStyle();
        $this->builder->text("Confirme que as próximas duas linhas ocupam apenas uma linha cada:");

        $this->linebreak();
        $this->linebreak();

        $string = '';
        for ($i = 0; $i < $this->printer->normalWidth; $i++) {
            $string .= (($i + 1) % 10);
        }

        $this->builder->text($string);

        $this->linebreak();

        $string = '';
        for ($i = 0; $i < $this->printer->condensedWidth; $i++) {
            $string .= (($i + 1) % 10);
        }

        $this->builder->textFont("C");
        $this->builder->text($string);

        $this->linebreak();
        $this->linebreak();

        $this->builder->textFont("A");
        $this->builder->text('Confirme se os seguintes caracteres especiais aparecem correctamente.');
        $this->linebreak();
        $this->builder->text('Deverão ser quatro acentuações de "a", "e", "i", "o", e ainda um c cedilhado e um símbolo do euro.');
        $this->linebreak();
        $this->linebreak();
        $this->builder->text('àáãâèéẽêiìíîòóõôç€');

        $this->linebreak();
        $this->linebreak();

        $this->builder->text('Confirme se a seguinte linha separadora de tabela aparece correctamente, se percorre toda a largura do talão, e se ocupa apenas uma linha:');
        $this->linebreak();
        $this->linebreak();

        $this->drawLine();
        $this->linebreak();
        $this->linebreak();


        if ($this->printer->hasCutter || $this->printer->hasDrawer) {
            $this->builder->textFont("A");
            $this->builder->textStyle(false, false, true);
            $this->builder->text("Testes de serviço");
            $this->linebreak();
            $this->linebreak();

            $this->builder->textStyle();

            if ($this->printer->hasDrawer) {
                $this->builder->text("Confirme se a impressora abriu a gaveta");
                $this->linebreak();

            }

            if ($this->printer->hasCutter) {
                $this->builder->text("Confirme se a impressora cortou o papel");
                $this->linebreak();
            }

        }

        $this->finish();
        return $this->builder->getPrintJob('json');
    }

}