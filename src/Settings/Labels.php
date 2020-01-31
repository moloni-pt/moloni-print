<?php

namespace MoloniPrint\Settings;

class Labels
{

    /**
     * Default labels in Portuguese
     * This labels can be changed by a documents/getPrintLabels API call object
     */

    public $adjustment = "Acertos";
    public $associated_documents = "Documentos relacionados";
    public $at_code = "Código AT";
    public $balance = "Saldo";
    public $beginning = "Início";
    public $cashflow = "Movimento de caixa";
    public $cashflow_close = "Fecho de caixa";
    public $cashflow_in = "Entrada de caixa";
    public $cashflow_open = "Abertura de caixa";
    public $cashflow_out = "Saída de caixa";
    public $cashflow_resume = "Consulta de caixa";
    public $cashflow_starting_amount = "Fundo de caixa";
    public $coin = "Moeda";
    public $commercial_discount = "Desconto comercial";
    public $commercial_registration_number = "R.C.C. Nº";
    public $company = "Empresa";
    public $conciliated = "Valor Conciliado";
    public $date = "Data";
    public $declared = "Declarado";
    public $departure_place = "Local Carga";
    public $designation = "Designação";
    public $destination_place = "Local Descarga";
    public $discount_short = "Desc.";
    public $difference = "Diferença";
    public $documents = "Documentos";
    public $document_created_at = "Documento gerado em";
    public $duplicate = "Duplicado";
    public $email = "Email";
    public $entity = "Entidade";
    public $entity_short = "Ent.";
    public $exchange_rate = "Câmbio";
    public $outflow = "Saídas";
    public $expedition_method = "Método de Expedição";
    public $expenses = "Despesas";
    public $final = "Final";
    public $financial_discount = "Desconto financeiro";
    public $gross_total = "Total Ilíquido";
    public $incidence = "Incidência";
    public $initial = "Inicial";
    public $invoiced = "Faturado";
    public $iva = "IVA";
    public $obs = "Observações";
    public $obs_short = "Obs.";
    public $offer_ticket = "Talão de troca";
    public $operator = "Operador";
    public $original = "Original";
    public $our_reference = "Enc./Orç.";
    public $payments = "Pagamentos";
    public $period = "Período";
    public $phone = "Telefone";
    public $powered_by = "Powered by Moloni | https://moloni.pt";
    public $price = "Preço";
    public $processed_by = "Processado por programa certificado Nº 1455/AT";
    public $products = "Artigos";
    public $products_availability_note = "Os Artigos e/ou Serviços faturados foram colocados/efetuados à disposição do adquirente à data";
    public $products_lines = "Linhas de artigos";
    public $products_qty_short = "Qtd. artigos";
    public $pvp_unit_short = "PVP Unit.";
    public $qty = "Qtd.";
    public $quadruplicate = "Quadruplicado";
    public $mb_references = "Referencias Multibanco";
    public $reference_short = "Refª";
    public $resume = "Resumo";
    public $sales = "Vendas";
    public $salesman = "Vendedor";
    public $second_way = "2ª Via";
    public $shipping = "Transporte";
    public $shippingMethod = "Método de Expedição";
    public $signature = "Assinatura";
    public $social_capital = "Capital Social";
    public $taxes = "Taxas";
    public $terminal = "Terminal";
    public $total = "Total";
    public $total_discounts = "Valor do Desconto";
    public $total_expenses = "Total despesas";
    public $total_sales = "Total vendas";
    public $triplicate = "Triplicado";
    public $undifferentiated = "Indiferenciado";
    public $value = "Valor";
    public $vehicle = "Viatura";
    public $vat = "Contribuinte";
    public $your_reference = "V/ Refª";

    public $document_types = [
        '1' => 'Fatura',
        '2' => 'Recibo',
        '3' => 'Nota de Crédito',
        '4' => 'Nota de Devolução',
        '5' => 'Venda a Dinheiro',
        '6' => 'Guia de Remessa',
        '7' => 'Nota de Encomenda de Fornecedor',
        '8' => 'Fatura de Fornecedor',
        '9' => 'Venda a Dinheiro Fornecedor',
        '10' => 'Recibo Fornecedor',
        '11' => 'Saldos Migrados (Faturas)',
        '12' => 'Avença',
        '13' => 'Fatura Pro forma',
        '14' => 'Orçamento',
        '15' => 'Guia de Transporte',
        '16' => 'Guia de Consignação',
        '17' => 'Saldos Migrados (Vendas a Dinheiro)',
        '18' => 'Saldos Migrados (Notas de Crédito)',
        '19' => 'Nota de Liquidação',
        '20' => 'Fatura Simplificada',
        '21' => 'Nota de Crédito de Fornecedor',
        '22' => 'Nota de Devolução de Fornecedor',
        '23' => 'Fatura Simplificada de Fornecedor',
        '24' => 'Saldos Migrados (Nota de Débito)',
        '25' => 'Saldos Migrados (Fatura Simplificada)',
        '26' => 'Nota de Débito de Fornecedor',
        '27' => 'Fatura/Recibo',
        '28' => 'Nota de Encomenda',
        '29' => 'Pedidos de Garantia de Fornecedor',
        '30' => 'Ficha de Serviço',
        '31' => 'Guia de Movimentação de Ativos Próprios',
        '32' => 'Nota de Devolução de Cliente',
        '33' => 'Pagamentos a Vendedores',
        '34' => 'Adiantamento',
        '35' => 'Devolução de Pagamento',
        '36' => 'Movimento de Stock',
        '37' => 'Documento Interno',
        '47' => 'Consulta de Mesa',
        '56' => 'Vendas Suspensas',
    ];

    public $exemption_reasons = [
        'M01' => 'Artigo 16.º n.º 6 do CIVA (Ou similar)',
        'M02' => 'Artigo 6.º do Decreto-Lei n.º 198/90, de 19 de Junho',
        'M03' => 'Exigibilidade de caixa',
        'M04' => 'Isento Artigo 13.º do CIVA (Ou similar)',
        'M05' => 'Isento Artigo 14.º do CIVA (Ou similar)',
        'M06' => 'Isento Artigo 15.º do CIVA (Ou similar)',
        'M07' => 'Isento Artigo 9.º do CIVA (Ou similar)',
        'M08' => 'IVA - Autoliquidação',
        'M09' => 'IVA - não confere direito a dedução',
        'M10' => 'IVA - Regime de isenção',
        'M11' => 'Regime particular do tabaco',
        'M12' => 'Regime da margem de lucro - Agências de viagens',
        'M13' => 'Regime da margem de lucro - Bens em segunda mão',
        'M14' => 'Regime da margem de lucro - Objetos de arte',
        'M15' => 'Regime da margem de lucro - Objetos de coleção e antiguidades',
        'M16' => 'Isento Artigo 14.º do RITI (Ou similar)',
        'M20' => 'IVA - Regime forfetário',
        'M99' => 'Não sujeito; não tributado (Ou similar)',
    ];

    /**
     * Labels constructor.
     * @param $printingLabels array
     */
    public function __construct($printingLabels = [])
    {
        $this->setPrintingLabels($printingLabels);
    }

    /**
     * Get the string of a label
     * if the label is not set return the param value
     * @param $label string
     * @return string
     */
    public function __get($label)
    {
        return isset($this->{$label}) ? $this->{$label} : $label;
    }

    /**
     * Define labels for printing
     * @param $printingLabels
     */
    private function setPrintingLabels($printingLabels)
    {
        if (!empty($printingLabels) && is_array($printingLabels)) {
            foreach ($printingLabels as $name => $value) {
                $this->{$name} = $value;
            }
        }
    }
}