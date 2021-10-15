<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Symfony\Component\DomCrawler\Crawler;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Client;
use Exception;
use Log;
use App\BaseGenerale;
use App\System;
use App\Theme;
use App\Type;
use Sunra\PhpSimple\HtmlDomParser;
use Goutte;
use DB;

/*
    https://codebriefly.com/how-to-handle-content-scraping-with-pagination-in-laravel
*/

class ContentCrawler extends Controller
{
    private $client;
    /**
     * Class __contruct
     */
    public function __construct()
    {
        $this->client = new Client([
                'timeout'   => 60,
                'verify'    => false
            ]);
    }

    public function display(){

        /*
        $s = '06/10/2011';
        $date = strtotime($s);
        $date = date('d/m/Y H:i:s', $date);

        dd($date);
        */

        $BaseGenerale = BaseGenerale::with(['systeme', 'theme', 'type'])
                    ->orderBy('date_exigence', 'DESC')
                    ->get()
        ;

        //var_dump($BaseGenerale);

        //dd($BaseGenerale);

        return view('display', compact('BaseGenerale'));
    }

    protected function fetchFullContent($selector)
    {
        try {

            $html = $this->getUrl();

            $crawler = new Crawler($html);

            //$crawler = $this->client->request('GET', $item_url);
            return $crawler->filter($selector)->html();
        } catch (\Exception $ex) {
            return "";
        }
    }

    public function testing()
    {

        try {

            $content = $this->getUrl();

            //dd($content);

            $crawler = new Crawler($content);

            $_this = $this;
            $data = $crawler->filter('.views-table tbody tr')
                            ->each(function (Crawler $node, $i) use($_this) {
                                return $_this->getNodeContent($node);
                            }
                        );
            dump($data);





            return 'Done';

        } catch ( Exception $e ) {
            echo $e->getMessage();
        }
    }

    public function getCrawlerContent()
    {

        try {

            $cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray(
                [
                    'SSESSe35fe072175e34ca4ac123670546d9ce' => 'UBdTGrYlx494WsVn5P7Q1BVQOUVtQdlMkxdW5VTI7a8',
                ],
                'keyveille.com'
            );

            for ($i=0; $i < 60 ; $i++) {

                $url = 'https://keyveille.com/admin/kv/text-base?page='.$i;
                //$url = 'https://keyveille.com/admin/kv/text-base';

                $response = $this->client->get($url, [
                    'headers' => [
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36'
                    ],
                    'allow_redirects' => true,
                    'cookies' => $cookieJar
                ]
                );

                $content = $response->getBody()->getContents();

                $crawler = new Crawler($content);

                $_this = $this;
                $data = $crawler->filter('.views-table tbody tr')
                                ->each(function (Crawler $node, $i) use($_this) {
                                    return $_this->getNodeContent($node);
                                }
                            );
                dump($data);

                $this->save($data);

                echo'i= '.$i;

            }



            return 'Done';

        } catch ( Exception $e ) {
            echo $e->getMessage();
        }
    }


        protected function save($content)
        {

            foreach ($content as $key => $data) {

                $checkExist = BaseGenerale::where('title', $data['title'])->first();

                if (!isset($checkExist->id)) {

                    $BaseGenerale = new BaseGenerale();
                    $BaseGenerale->title = isset($data['title'] ) ? $data['title'] : "";

                    $Theme = Theme::where('name', $data['theme'])->first();

                    if (is_null($Theme)) {
                        $Theme = Theme::create(['name' => $data['theme']]);
                    }

                    $System = System::where('name', $data['systeme'])->first();

                    if (is_null($System)) {
                        $System = System::create(['name' => $data['systeme']]);
                    }

                    $Type = Type::where('name', $data['type'])->first();
                    if (is_null($Type)) {
                        $Type = Type::create(['name' => $data['type']]);
                    }

                    $BaseGenerale->systeme_id = $System->id;
                    $BaseGenerale->theme_id = $Theme->id;
                    $BaseGenerale->type_id = $Type->id;
                    $BaseGenerale->description = isset($data['description'] ) ? $data['description']  : "";
                    $BaseGenerale->pdf = isset($data['pdf'] ) ? $data['pdf']  : "";

                    $date_exigence = isset($data['date_exigence'] ) ? $data['date_exigence']  : "";

                    $date_exigence = date('Y-m-d H:i:s', strtotime($date_exigence));

                    $BaseGenerale->date_exigence = $date_exigence;

                    $BaseGenerale->save();
                    echo'Saved. ';
                }
                else{
                    echo'exist. ';
                }
            }
        }


    private function initCrawler($url){

        dump($url);

        $baseURL = 'https://keyveille.com';

        $url = $baseURL.$url;

        $cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray(
            [
                'SSESSe35fe072175e34ca4ac123670546d9ce' => 'UBdTGrYlx494WsVn5P7Q1BVQOUVtQdlMkxdW5VTI7a8',
            ],
            'keyveille.com'
        );

        $response = $this->client->get($url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36'
            ],
            'allow_redirects' => true,
            'connect_timeout' => 3.14,
            'cookies' => $cookieJar
        ]
        );

        $content = $response->getBody()->getContents();

        $crawler = new Crawler( $content );

        return $crawler;
    }


    /**
     * Pagination records
     */
    private function paginate($crawler_instance, $max_page_allowed = 3)
    {
        $instance = $crawler_instance;
        $paginate = $instance->filter('.pager-item')->count() ? $instance->filter('.pager-item a')->attr('href') : 0;

        //dd($paginate);

        $current_page_no = \explode('=',$paginate)[1] ?? 0 ;

        $data = [];
        $_this = $this;
        if( $paginate !== 0 && $current_page_no <= $max_page_allowed ) {
            $childData = $this->subCrawler($paginate);
            $data = array_merge($data, $childData);
        }

        dd($data);

        return $data;
    }
    /**
     * Sub-Crawler for pagination records
     */
    private function subCrawler($url)
    {
        $_this = $this;
        $data = [];
        $subCrawler = $this->initCrawler($url);
        $childData = $subCrawler->filter('.views-table tbody tr')
                    ->each(function (Crawler $node, $i) use($_this) {
                        return $_this->getNodeContent($node);
                    });

        //dd($childData);

        $data = array_merge( $data, $childData );

        $subData = $this->paginate( $subCrawler, $_this->crawler_page_limit ?? 3 ); // Call recursively
        $data = array_merge( $data, $subData );

        //dd($data);
        return $data;
    }

    /**
     * Check is content available
     */
    private function hasContent($node)
    {
        return $node->count() > 0 ? true : false;
    }
    /**
     * Get node values
     * @filter function required the identifires, which we want to filter from the content.
     */
    private function getNodeContent($node)
    {

        $description_url = $this->hasContent($node->filter('.views-field-title span a')) != false ? $node->filter('.views-field-title span a')->attr('href') : '';

        $cookieJar = \GuzzleHttp\Cookie\CookieJar::fromArray(
            [
                'SSESSe35fe072175e34ca4ac123670546d9ce' => 'UBdTGrYlx494WsVn5P7Q1BVQOUVtQdlMkxdW5VTI7a8',
            ],
            'keyveille.com'
        );

        $url = 'https://keyveille.com'.$description_url;

        $response = $this->client->get($url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36'
            ],
            'allow_redirects' => true,
            'cookies' => $cookieJar
        ]
        );

        $content = $response->getBody()->getContents();

        $crawler = new Crawler($content);

        $_this = $this;
        $description = $crawler->filter('.field-name-body .even')->html();
        //$description = str_replace("*", "<br><br> *", $description);

        $title = $this->hasContent($node->filter('.views-field-title')) != false ? $node->filter('.views-field-title')->html() : '';
        $title = strip_tags(str_replace("Résumé</a>", "", $title));

        $array = [
            'systeme' => $this->hasContent($node->filter('.views-field-field-text-systeme')) != false ? $node->filter('.views-field-field-text-systeme')->text() : '',
            'theme' => $this->hasContent($node->filter('.views-field-field-text-theme')) != false ? $node->filter('.views-field-field-text-theme')->text() : '',
            'type' => $this->hasContent($node->filter('.views-field-field-text-type')) != false ? $node->filter('.views-field-field-text-type')->text() : '',
            'title' => $title,
            'description' => $description,
            'pdf' => $this->hasContent($node->filter('.views-field-field-text-piece-jointe-pdf a')) != false ? $node->filter('.views-field-field-text-piece-jointe-pdf a')->attr('href') : '',
            'date_exigence' => $this->hasContent($node->filter('.views-field-field-text-date-exigence')) != false ? $node->filter('.views-field-field-text-date-exigence')->text() : '',
        ];

        return $array;
    }

    private function getUrl()
    {
        return $html = <<<'HTML'


        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML+RDFa 1.0//EN"
          "http://www.w3.org/MarkUp/DTD/xhtml-rdfa-1.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr" version="XHTML+RDFa 1.0" dir="ltr"
          xmlns:content="http://purl.org/rss/1.0/modules/content/"
          xmlns:dc="http://purl.org/dc/terms/"
          xmlns:foaf="http://xmlns.com/foaf/0.1/"
          xmlns:og="http://ogp.me/ns#"
          xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
          xmlns:sioc="http://rdfs.org/sioc/ns#"
          xmlns:sioct="http://rdfs.org/sioc/types#"
          xmlns:skos="http://www.w3.org/2004/02/skos/core#"
          xmlns:xsd="http://www.w3.org/2001/XMLSchema#">

        <head profile="http://www.w3.org/1999/xhtml/vocab">
          <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <link rel="shortcut icon" href="https://keyveille.com/sites/default/files/kv_logo_1.png" type="image/png" />
        <meta name="Generator" content="Drupal 7 (http://drupal.org)" />
          <title>Text Base | KeyVeille</title>
          <style type="text/css" media="all">
        @import url("https://keyveille.com/modules/system/system.messages.css?pyk0jb");
        </style>
        <style type="text/css" media="all">
        @import url("https://keyveille.com/sites/all/libraries/chosen/chosen.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/chosen/css/chosen-drupal.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/jquery_update/replace/ui/themes/base/minified/jquery.ui.core.min.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/jquery_update/replace/ui/themes/base/minified/jquery.ui.theme.min.css?pyk0jb");
        </style>
        <style type="text/css" media="all">
        @import url("https://keyveille.com/sites/all/modules/contrib/date/date_api/date.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/date/date_popup/themes/datepicker.1.7.css?pyk0jb");
        @import url("https://keyveille.com/modules/field/theme/field.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/views/css/views.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/ckeditor/css/ckeditor.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/admin_menu/admin_menu.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/admin_menu/admin_menu_toolbar/admin_menu_toolbar.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/rubik/shortcut.css?pyk0jb");
        </style>
        <style type="text/css" media="all">
        @import url("https://keyveille.com/sites/all/modules/contrib/ctools/css/ctools.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/panels/css/panels.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/modules/contrib/ctools/css/modal.css?pyk0jb");
        </style>

        <!--[if lte IE 7]>
        <style type="text/css" media="all">
        @import url("https://keyveille.com/sites/all/themes/tao/ie.css?pyk0jb");
        </style>
        <![endif]-->
        <style type="text/css" media="all">
        @import url("https://keyveille.com/sites/all/themes/tao/reset.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/tao/base.css?pyk0jb");
        </style>
        <style type="text/css" media="screen">
        @import url("https://keyveille.com/sites/all/themes/tao/drupal.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/rubik/core.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/rubik/icons.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/rubik/style.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/rubik/jquery.ui.theme.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/rubik/css/custom.css?pyk0jb");
        @import url("https://keyveille.com/sites/all/themes/rubik/fonts/glyphicons_pro/glyphicons.min.css?pyk0jb");
        </style>
        <style type="text/css" media="print">
        @import url("https://keyveille.com/sites/all/themes/rubik/print.css?pyk0jb");
        </style>
          <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/jquery_update/replace/jquery/1.7/jquery.min.js?v=1.7.2"></script>
        <script type="text/javascript" src="https://keyveille.com/misc/jquery-extend-3.4.0.js?v=1.7.2"></script>
        <script type="text/javascript" src="https://keyveille.com/misc/jquery.once.js?v=1.2"></script>
        <script type="text/javascript" src="https://keyveille.com/misc/drupal.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/jquery_update/replace/ui/ui/minified/jquery.ui.core.min.js?v=1.10.2"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/jquery_update/replace/ui/external/jquery.cookie.js?v=67fb34f6a866c40d0570"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/jquery_update/replace/misc/jquery.form.min.js?v=2.69"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/libraries/chosen/chosen.jquery.min.js?v=1.1.0"></script>
        <script type="text/javascript" src="https://keyveille.com/misc/ajax.js?v=7.67"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/jquery_update/js/jquery_update.js?v=0.0.1"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/admin_menu/admin_devel/admin_devel.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/admin_menu/admin_menu.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/admin_menu/admin_menu_toolbar/admin_menu_toolbar.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/default/files/languages/fr_zOD5h82vRGI42FI6N6-ONNaMFO_E-GXa-vAMd5CJCIc.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/views/js/base.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/misc/progress.js?v=7.67"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/better_exposed_filters/better_exposed_filters.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/misc/autocomplete.js?v=7.67"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/ctools/js/modal.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/views/js/ajax_view.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/modules/contrib/chosen/chosen.js?v=1.1.0"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/themes/rubik/js/rubik.js?pyk0jb"></script>
        <script type="text/javascript" src="https://keyveille.com/sites/all/themes/rubik/js/custom.js?pyk0jb"></script>
        <script type="text/javascript">
        <!--//--><![CDATA[//><!--
        jQuery.extend(Drupal.settings, {"basePath":"\/","pathPrefix":"","ajaxPageState":{"theme":"rubik","theme_token":"Z3EdAuRiRZ-iaVb5nTw0aamJqCXzPcyU_sTI61EGtA8","jquery_version":"1.7","js":{"sites\/all\/modules\/custom\/kv_common\/js\/kv_common_ajax_view.js":1,"sites\/all\/modules\/contrib\/jquery_update\/replace\/jquery\/1.7\/jquery.min.js":1,"misc\/jquery-extend-3.4.0.js":1,"misc\/jquery.once.js":1,"misc\/drupal.js":1,"sites\/all\/modules\/contrib\/jquery_update\/replace\/ui\/ui\/minified\/jquery.ui.core.min.js":1,"sites\/all\/modules\/contrib\/jquery_update\/replace\/ui\/external\/jquery.cookie.js":1,"sites\/all\/modules\/contrib\/jquery_update\/replace\/misc\/jquery.form.min.js":1,"sites\/all\/libraries\/chosen\/chosen.jquery.min.js":1,"misc\/ajax.js":1,"sites\/all\/modules\/contrib\/jquery_update\/js\/jquery_update.js":1,"sites\/all\/modules\/contrib\/admin_menu\/admin_devel\/admin_devel.js":1,"sites\/all\/modules\/contrib\/admin_menu\/admin_menu.js":1,"sites\/all\/modules\/contrib\/admin_menu\/admin_menu_toolbar\/admin_menu_toolbar.js":1,"public:\/\/languages\/fr_zOD5h82vRGI42FI6N6-ONNaMFO_E-GXa-vAMd5CJCIc.js":1,"sites\/all\/modules\/contrib\/views\/js\/base.js":1,"misc\/progress.js":1,"sites\/all\/modules\/contrib\/better_exposed_filters\/better_exposed_filters.js":1,"misc\/autocomplete.js":1,"sites\/all\/modules\/contrib\/ctools\/js\/modal.js":1,"sites\/all\/modules\/contrib\/views\/js\/ajax_view.js":1,"sites\/all\/modules\/contrib\/chosen\/chosen.js":1,"sites\/all\/themes\/rubik\/js\/rubik.js":1,"sites\/all\/themes\/rubik\/js\/custom.js":1},"css":{"modules\/system\/system.messages.css":1,"sites\/all\/libraries\/chosen\/chosen.css":1,"sites\/all\/modules\/contrib\/chosen\/css\/chosen-drupal.css":1,"misc\/ui\/jquery.ui.core.css":1,"misc\/ui\/jquery.ui.theme.css":1,"sites\/all\/modules\/contrib\/date\/date_api\/date.css":1,"sites\/all\/modules\/contrib\/date\/date_popup\/themes\/datepicker.1.7.css":1,"modules\/field\/theme\/field.css":1,"sites\/all\/modules\/contrib\/views\/css\/views.css":1,"sites\/all\/modules\/contrib\/ckeditor\/css\/ckeditor.css":1,"sites\/all\/modules\/contrib\/admin_menu\/admin_menu.css":1,"sites\/all\/modules\/contrib\/admin_menu\/admin_menu_toolbar\/admin_menu_toolbar.css":1,"modules\/shortcut\/shortcut.css":1,"sites\/all\/modules\/contrib\/ctools\/css\/ctools.css":1,"sites\/all\/modules\/contrib\/panels\/css\/panels.css":1,"sites\/all\/modules\/contrib\/ctools\/css\/modal.css":1,"sites\/all\/themes\/tao\/ie.css":1,"sites\/all\/themes\/tao\/reset.css":1,"sites\/all\/themes\/tao\/base.css":1,"sites\/all\/themes\/tao\/drupal.css":1,"sites\/all\/themes\/rubik\/core.css":1,"sites\/all\/themes\/rubik\/icons.css":1,"sites\/all\/themes\/rubik\/style.css":1,"sites\/all\/themes\/rubik\/jquery.ui.theme.css":1,"sites\/all\/themes\/rubik\/css\/custom.css":1,"sites\/all\/themes\/rubik\/fonts\/glyphicons_pro\/glyphicons.min.css":1,"sites\/all\/themes\/rubik\/print.css":1}},"add-text":{"modalSize":{"type":"fixed","width":600,"height":220,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_manage_text\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"view-text":{"modalSize":{"type":"fixed","width":700,"height":500,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_manage_text\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"view-penality":{"modalSize":{"type":"fixed","width":600,"height":200,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_manage_text\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"update-system":{"modalSize":{"type":"fixed","width":700,"height":450,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_manage_text\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"simple-modal-remove-style":{"modalSize":{"type":"fixed","width":600,"height":220,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_text_base\/kv_text_base_ui\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"simple-modal-department-style":{"modalSize":{"type":"fixed","width":600,"height":320,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_text_base\/kv_text_base_ui\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"simple-modal-applicability-style":{"modalSize":{"type":"fixed","width":600,"height":220,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_text_base\/kv_text_base_ui\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"simple-modal-analysis-style":{"modalSize":{"type":"fixed","width":1000,"height":580,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/custom\/kv_text_base\/kv_text_base_ui\/images\/ajax-loader.gif\u0022 alt=\u0022Chargement...\u0022 title=\u0022En cours de chargement\u0022 \/\u003E"},"delete-confirm-style":{"modalSize":{"type":"fixed","width":600,"height":220,"addWidth":20,"addHeight":15},"modalOptions":{"opacity":0.5,"background-color":"#000000"},"animation":"fadeIn"},"better_exposed_filters":{"datepicker":false,"slider":false,"settings":[],"autosubmit":false,"views":{"general_text":{"displays":{"general_text_block":{"filters":{"title":{"required":false},"field_text_status_value":{"required":false},"field_text_systeme_tid":{"required":false},"field_text_theme_tid":{"required":false},"field_text_type_tid":{"required":false}}}}}}},"chosen":{"selector":"","minimum_single":20,"minimum_multiple":0,"minimum_width":200,"options":{"allow_single_deselect":false,"disable_search":false,"disable_search_threshold":1,"search_contains":false,"placeholder_text_multiple":"Choose some options","placeholder_text_single":"Choose an option","no_results_text":"No results match","inherit_select_classes":true}},"urlIsAjaxTrusted":{"\/admin\/text-base\/general-text-base\/export\/1%2B2%2B71%2B70%2B7%2B6%2B67%2B3%2B84%2B830%2B1098":true,"\/views\/ajax":true},"CToolsModal":{"loadingText":"Chargement...","closeText":"Fermer","closeImage":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/contrib\/ctools\/images\/icon-close-window.png\u0022 alt=\u0022Fermer\u0022 title=\u0022Fermer\u0022 \/\u003E","throbber":"\u003Cimg typeof=\u0022foaf:Image\u0022 src=\u0022https:\/\/keyveille.com\/sites\/all\/modules\/contrib\/ctools\/images\/throbber.gif\u0022 alt=\u0022En cours de chargement\u0022 title=\u0022Chargement...\u0022 \/\u003E"},"views":{"ajax_path":"\/views\/ajax","ajaxViews":{"views_dom_id:738c156864e7911e5a3559a9589fb520":{"view_name":"general_text","view_display_id":"general_text_block","view_args":"1+2+71+70+7+6+67+3+84+830+1098","view_path":"admin\/kv\/text-base","view_base_path":"admin\/text-base\/general-text-base\/export","view_dom_id":"738c156864e7911e5a3559a9589fb520","pager_element":0}}},"tableHeaderOffset":"Drupal.admin.height","admin_menu":{"destination":"destination=admin\/kv\/text-base","hash":"4a554ce52294806073985f60b12bda8e","basePath":"\/admin_menu","margin_top":1,"position_fixed":1,"toolbar":{"activeTrail":"\/admin\/kv"}}});
        //--><!]]>
        </script>
        </head>
        <body class="html not-front logged-in no-sidebars page-admin page-admin-kv page-admin-kv-text-base tao" >
          <div id="skip-link">
                  <a href="#main-content" class="element-invisible element-focusable">Aller au contenu principal</a>


          </div>
            <div id='branding'><div class='limiter clearfix'>
          <div class='breadcrumb clearfix'><span class='breadcrumb-link breadcrumb-depth-0'><a href="/">Accueil</a></span><span class='breadcrumb-link breadcrumb-depth-1'><a href="/admin/kv" title="KeyVeille advanced search in Text Base.">Key Veille</a></span><span class='breadcrumb-link breadcrumb-depth-2'><strong>Text Base</strong></span></div>
              <ul class="links secondary-menu"><li class="menu-2 first"><a href="/user">Mon compte</a></li>
        <li class="menu-15 last"><a href="/user/logout">Se déconnecter</a></li>
        </ul>  </div></div>

        <div id='page-title'><div class='limiter clearfix'>
          <div class='tabs clearfix'>
                  <ul class='primary-tabs links clearfix'><li class="active"><a href="/admin/kv/text-base" class="active">Base générale de texte<span class="element-invisible">(onglet actif)</span></a></li>
        <li><a href="/admin/kv/text-base/my-text-base">Ma Base de Texte</a></li>
        <li><a href="/admin/kv/text-base/analyse-conformite">Analyse conformité</a></li>
        <li><a href="/admin/kv/text-base/obsolete-text">Texte Périmé</a></li>
        </ul>
              </div>
            <h1 class='page-title path-admin-kv-text-base- path-admin-kv-text-base path-admin-kv path-admin'>
            <span class='icon'></span>    Text Base  </h1>
            </div></div>


        <div id='page'><div id='main-content' class='limiter clearfix'>
            <div id='content' class='page-content clearfix'>
              <div class="region region-content">

        <div id="block-system-main" class="block block-system">




              <div class="block-content clearfix"><div id="base-text-msg"></div>
        <div class="view view-general-text view-id-general_text view-display-id-general_text_block view-dom-id-738c156864e7911e5a3559a9589fb520">

          <div class="text-count sample">
            Nombre total 925  </div>

              <div class="view-filters">
              <form action="/admin/text-base/general-text-base/export/1%2B2%2B71%2B70%2B7%2B6%2B67%2B3%2B84%2B830%2B1098" method="get" id="views-exposed-form-general-text-general-text-block" accept-charset="UTF-8"><div><div class="views-exposed-form">
          <div class="views-exposed-widgets clearfix">
                  <div id="edit-title-wrapper" class="views-exposed-widget views-widget-filter-title">
                          <label for="edit-title">
                    Mot clé          </label>
                                <div class="views-widget">
                  <div class="form-item form-type-textfield form-item-title">
         <input class="fluid form-text form-autocomplete" type="text" id="edit-title" name="title" value="" size="" maxlength="128" /><input type="hidden" id="edit-title-autocomplete" value="https://keyveille.com/index.php?q=autocomplete_filter/title/general_text/general_text_block/1%2B2%2B71%2B70%2B7%2B6%2B67%2B3%2B84%2B830%2B1098" disabled="disabled" class="autocomplete" />
        </div>
                </div>
                      </div>
                  <div id="edit-field-text-status-value-wrapper" class="views-exposed-widget views-widget-filter-field_text_status_value">
                          <label for="edit-field-text-status-value">
                    Statut          </label>
                                <div class="views-widget">
                  <div class="form-item form-type-select form-item-field-text-status-value">
         <select id="edit-field-text-status-value" name="field_text_status_value" class="form-select"><option value="All" selected="selected">- Tout -</option><option value="ts">TS</option><option value="mp">MP</option><option value="mc">MC</option></select>
        </div>
                </div>
                      </div>
                  <div id="edit-field-text-systeme-tid-wrapper" class="views-exposed-widget views-widget-filter-field_text_systeme_tid">
                          <label for="edit-field-text-systeme-tid">
                    Système          </label>
                                <div class="views-widget">
                  <div class="form-item form-type-select form-item-field-text-systeme-tid">
         <select id="edit-field-text-systeme-tid" name="field_text_systeme_tid" class="form-select"><option value="All" selected="selected">- Tout -</option><option value="1">Env. - Securite Tn</option><option value="2">Environnement Tn</option><option value="71">Logistique / Env Tn</option><option value="70">Logistique / SST Tn</option><option value="7">Métrologie Tn</option><option value="6">Qualite Tn</option><option value="67">SST - RS Tn</option><option value="3">SST Tn</option><option value="84">Logistique/ Env-SST Tn</option><option value="830">Hygiène Tn</option><option value="1098">Energie TN</option></select>
        </div>
                </div>
                      </div>
                  <div id="edit-field-text-theme-tid-wrapper" class="views-exposed-widget views-widget-filter-field_text_theme_tid">
                          <label for="edit-field-text-theme-tid">
                    Thème          </label>
                                <div class="views-widget">
                  <div class="form-item form-type-select form-item-field-text-theme-tid">
         <select id="edit-field-text-theme-tid" name="field_text_theme_tid" class="form-select"><option value="All" selected="selected">- Tout -</option><option value="89">Abattage</option><option value="90">Accidents de travail &amp; maladies professionnelles</option><option value="953">Achat de matière</option><option value="1065">Acides et bases caustiques</option><option value="1147">Activité anesthésique</option><option value="1190">Additifs alimentaires / Contaminants et Résidus</option><option value="1118">Additifs alimentaires animales</option><option value="948">Agréage</option><option value="841">Agrément sanitaire</option><option value="97">Agriculture biologique</option><option value="640">Agro / Produit de la Pêche</option><option value="637">Agro/Denrée alimentaire</option><option value="635">Agro/Étiquetage et Emballage</option><option value="1117">Alimentation animale</option><option value="98">Alimentation des  animaux</option><option value="99">Amiante</option><option value="869">Analyses pratiquées en urgence aux établissements sanitaires privés</option><option value="946">Appelation d&#039;origine contrôlée</option><option value="100">Assurance des marchandises</option><option value="939">Assurance maladie</option><option value="1099">Audit Energétique</option><option value="1141">Autorisations</option><option value="101">Avantages sociaux</option><option value="778">barème d&#039;agréage du blé</option><option value="104">Bâtiment et Travaux publiques</option><option value="102">Beurre/ Prix</option><option value="865">Biologie médicale</option><option value="230">Boissons alcoolisées</option><option value="231">Boissons non alcoolisées</option><option value="833">Bonnes pratiques d&#039;élevage</option><option value="802">Bonnes pratiques de fabrication</option><option value="103">Bruit et nuisance sonore</option><option value="808">Cahiers des charges</option><option value="969">Caractéristiques des matières</option><option value="105">Caractéristiques microbiologiques</option><option value="760">caractéristiques produits</option><option value="999">Caractéristiques techniques</option><option value="106">Carrières</option><option value="1167">Cas suspects</option><option value="508">CCS Avenant / Assurance</option><option value="472">CCS Avenant / Bâtiment et travaux publics</option><option value="236">CCS Avenant / Boissons alcoolisées</option><option value="235">CCS Avenant / Boissons gazeuses non alcoolisées, sirops et eaux minérales.</option><option value="92">CCS Avenant / Bonneterie et Confection</option><option value="516">CCS Avenant / Boulangerie</option><option value="526">CCS Avenant / Cafés,Bars et restaurants</option><option value="513">CCS Avenant / Cliniques privées</option><option value="476">CCS Avenant / Commerce et distribution du pétrole et de tous ses dérivés</option><option value="480">CCS Avenant / Concessionnaires et constructeurs</option><option value="726">CCS Avenant / confiserie, biscuiterie, chocolaterie et pâtisserie.</option><option value="241">CCS Avenant / Construction métallique</option><option value="482">CCS Avenant / Eléctricité et éléctronique</option><option value="491">CCS Avenant / Explosifs</option><option value="492">CCS Avenant / Fabricant de produit de toilette et de parfumerie</option><option value="372">CCS Avenant / fonderie, métallurgie et construction mécanique</option><option value="242">CCS Avenant / Gardiennage</option><option value="744">CCS Avenant / Gestion des déchets solides et liquides</option><option value="232">CCS Avenant / Hôtels Classés Touristiques et Etablissements Similaires</option><option value="247">CCS Avenant / Industrie de la Chaussure et des Articles Chaussants</option><option value="486">CCS Avenant / Industrie de transformation du plastique</option><option value="722">CCS Avenant / Industrie du bois, du meuble et du liège</option><option value="752">CCS Avenant / industrie du cuir et peaux</option><option value="522">CCS Avenant / Industrie laitière et ses dérivés</option><option value="234">CCS Avenant / Lait et dérivés</option><option value="520">CCS Avenant / Location des véhicules</option><option value="478">CCS Avenant / Matériel agricole et génie civil</option><option value="489">CCS Avenant / Mécanique générale et des station de vente de carburant.</option><option value="499">CCS Avenant / Pâte alimentaire et couscous</option><option value="94">CCS Avenant / Pétrole et dérivés</option><option value="529">CCS Avenant / Presse écrite</option><option value="237">CCS Avenant / Produits d&#039;entretien et insecticides</option><option value="95">CCS Avenant / Teintureries et Blanchisseries</option><option value="96">CCS Avenant / Textile</option><option value="497">CCS Avenant / Transport routier de marchandises</option><option value="484">CCS Avenant /Industrie de l&#039;imprimerie/ la reliure/ la brochure / la  transformation du carton et la photographie.</option><option value="724">CCS Avenant/ commerce de gros, demi-gros et détail.</option><option value="474">CCS Avenant/ commerce des materiaux de construction du bois et des produits siderurgiques</option><option value="699">CCS Avenant/ Conserves et semi conserves alimentaires</option><option value="706">CCS Avenant/ Industrie des matériaux de construction</option><option value="720">CCS Avenant/ Salines</option><option value="857">CCS Avenant/ Savonneries, Raffineries et Usines d&#039;extraction d&#039;huile de grignons</option><option value="1178">CCS/ Cliniques de dialyse</option><option value="868">CENTRE d&#039;hémodialyse</option><option value="1013">Centre de Collecte</option><option value="887">Centres d&#039;hémodialyse</option><option value="867">Centres de thalassotherapie</option><option value="108">Céréales</option><option value="952">Chambres d&#039;hôtes</option><option value="1152">Chargement/ Déchargement</option><option value="1061">Ciment chirurgical</option><option value="109">Circuits de distribution des produits agroalimentaires</option><option value="897">Classement Des Hôtels</option><option value="959">Classement des restaurants</option><option value="225">Classification des Vins</option><option value="961">Club hippique</option><option value="1101">Co-génération</option><option value="1034">Code de déontologie</option><option value="687">Code de l&#039;environnement</option><option value="111">Code des eaux</option><option value="1114">Code des sociétés commerciales</option><option value="112">Code du travail</option><option value="255">Code du travail maritime</option><option value="955">Codex Alimentarius</option><option value="1024">Collecte de céréales</option><option value="113">Commerce électronique</option><option value="644">Commerce et distribution</option><option value="114">Commerce extérieur</option><option value="1033">Concurrence et Prix</option><option value="932">Condition d&#039;exercice des Ets publics</option><option value="940">Conditions d&#039;exercice</option><option value="1031">Conditions d&#039;exercice de la pêche</option><option value="1021">Conditions d&#039;exercice des activités laitières</option><option value="863">Conditions d&#039;exercice des Ets privés</option><option value="957">Conditions de commercialisation</option><option value="117">Conditions de commercialisation et de production</option><option value="958">Conditions de conditionnement</option><option value="1022">Conditions de paiement, de stockage et de rétrocession</option><option value="960">Conditions de stockage</option><option value="118">Conditions de transport d&#039;animaux et sous-produits</option><option value="119">Conditions de travail</option><option value="122">Conditions de travail/ Travail des femmes</option><option value="962">Conditions de vente</option><option value="931">Conditions d’exercice des Ets publics</option><option value="950">Conditions logistiques</option><option value="956">Conditions sanitaires</option><option value="1027">Conserves de piments &quot;Harissa&quot;</option><option value="1086">Constat de Décès</option><option value="124">Construction &amp; Bâtiment</option><option value="159">Consultation du personnel</option><option value="1100">Consultation préalable</option><option value="803">Contrat OMV</option><option value="1035">Contributions économiques</option><option value="643">Contrôle</option><option value="219">Contrôle des fournisseurs</option><option value="1006">Contrôle Des Opérations</option><option value="215">Contrôle métrologique</option><option value="783">contrôle qualité de l&#039;eau</option><option value="769">contrôle sanitaire</option><option value="770">contrôle sanitaire vétérinaire</option><option value="914">Contrôle social</option><option value="1051">Contrôle technique à l&#039;importation</option><option value="125">Convention collective cadre</option><option value="246">Déchargement &amp; Transbordement</option><option value="127">Déclaration Universelle des Droits de l&#039;Homme</option><option value="184">Délai de préavis</option><option value="954">Denrée alimentaire</option><option value="912">Dialogue social</option><option value="132">Discrimination</option><option value="886">Dispositifs médicaux/ Autres produits de santé</option><option value="126">Dispositions fiscales</option><option value="134">Distribution</option><option value="133">Diversité biologique</option><option value="776">DOC MBG Borj Cedria</option><option value="592">DOC Sohatram</option><option value="781">Doc Spécifiques</option><option value="617">DOC Techniplast</option><option value="655">DOC/ Meri</option><option value="129">Domaine forestier</option><option value="908">Droits de l&#039;homme</option><option value="690">Eau</option><option value="942">Eco-tourisme</option><option value="131">Ecolabel</option><option value="824">Elevage</option><option value="136">Emballage et Marquage/Exportation</option><option value="249">Embauchage</option><option value="1103">Energies Renouvelables</option><option value="135">Entrepôsage</option><option value="1010">Entretien Et Assainissement</option><option value="866">Equipements et Matériels lourds</option><option value="1108">Equipements utilisés dans la maîtrise de l&#039;énergie</option><option value="1176">Ergonomie</option><option value="1105">Etablissement de service énergétique</option><option value="137">Établissements dangereux</option><option value="878">Etablissements d’hygiène</option><option value="628">Ethique</option><option value="1102">Etiquetage</option><option value="139">Étiquetage et Emballage</option><option value="140">Étude d&#039;impact</option><option value="1001">Etude d&#039;impact / vocation des terres</option><option value="688">Études d&#039;impacts environnementaux</option><option value="658">Exigences clients ( MERI)</option><option value="453">Expérimentation</option><option value="572">Expérimentation/ Médicaments</option><option value="141">Exportation de l&#039;huile</option><option value="761">Fabrication de chocolaterie</option><option value="793">Fabrication et contrôle qualité des médicaments vétérinaires</option><option value="1025">Fabrication et Vente du Pain</option><option value="968">Formation</option><option value="874">Formation dans les spécialités para-médicales</option><option value="913">Formation professionnelle</option><option value="859">Fruits et légumes</option><option value="1151">Gens de mer</option><option value="144">Gestion des déchets et leur élimination</option><option value="1095">Gestion des dossiers médicaux</option><option value="145">Gestion des hydrocarbures</option><option value="641">Gestion des intrants</option><option value="143">Gestion des matières dangereuses</option><option value="146">Gestion des rejets liquides</option><option value="1085">Greffe d&#039;Organe</option><option value="838">Guide des investisseurs et promoteurs</option><option value="148">Homologation des normes</option><option value="239">Huile alimentaire / Prix</option><option value="210">Huile d&#039;olive / Exportation</option><option value="149">Huile d&#039;olive / Prix Qualité</option><option value="727">Huile d&#039;olive : Exportation</option><option value="150">Huile d&#039;olive : Mesures incitatives</option><option value="151">Huiles lubrifiantes et filtre à huiles usagés</option><option value="1007">Hygiène Corporelle</option><option value="1050">Hygiène sanitaire</option><option value="947">Importation</option><option value="1023">Importation de mais grain et tourteaux de soja</option><option value="1016">Importation de produit</option><option value="659">Incitation</option><option value="1107">Incitations</option><option value="152">Indemnisation des travailleurs</option><option value="155">Inspection de travail</option><option value="1009">Installations Alimentaires</option><option value="737">Investissement agricole</option><option value="642">Irrigation</option><option value="156">Label qualité</option><option value="888">laboratoires d&#039;analyses</option><option value="157">Lait</option><option value="174">Lait / Prix présidentiel</option><option value="1019">Lait infantile</option><option value="158">Lait régénéré / Prix de vente</option><option value="238">Liberté syndicale</option><option value="226">Licenciement</option><option value="764">Limites Maximales Tolérées en Résidus de Pesticides</option><option value="632">Lutte contre la corruption</option><option value="1059">Maintenance des équipements médicaux</option><option value="160">Maîtrise de l&#039;énergie</option><option value="823">Maladies animales</option><option value="800">maladies végétales</option><option value="161">Marchés Publics</option><option value="804">Matrice d&#039;évaluation</option><option value="1043">Médecine d&#039;urgence</option><option value="1083">Médecine de la Reproduction</option><option value="190">Médecine du travail</option><option value="1077">Médecine reproductive</option><option value="889">Médicaments pour usage urgent</option><option value="794">Médicaments vétérinaires</option><option value="568">Médicaments/ Mise sur le marché</option><option value="162">Mesures incitatives</option><option value="1120">Méthodes d&#039;analyse</option><option value="163">Métrologie légale</option><option value="130">Milieu Naturel et Maritime</option><option value="183">Mines et carrières</option><option value="799">Mode biologique</option><option value="1030">Mollusques : Conditions sanitaires</option><option value="1066">Nomenclature et Prescription des médicaments</option><option value="164">Normalisation et qualité</option><option value="851">Normes</option><option value="165">Obtentions Végétales</option><option value="1052">Ordonnance médicale</option><option value="891">Organisation de l&#039;exercice de la profession de médecin dentiste</option><option value="890">Organisation de l&#039;exercice des professions médicales</option><option value="1042">Organisation de l&#039;exercice des professions paramédicales</option><option value="892">Organisation de l&#039;exercice des professions pharmaceutiques</option><option value="777">Organisation de la distribution du son de blé</option><option value="1064">Organisation de la pharmacie hospitalière</option><option value="229">Organisation de la Sécurité</option><option value="166">Organisation Internationale de Travail</option><option value="248">Organisation sanitaire</option><option value="169">Pénalités</option><option value="1106">Performance Energétique</option><option value="253">Période d&#039;essai</option><option value="168">Pesticides</option><option value="1014">Plan Directeur</option><option value="170">Pollution marine</option><option value="1149">Ports maritimes de commerce</option><option value="911">Pratiques disciplinaires</option><option value="1063">Prélèvement et greffe d&#039;organes</option><option value="963">Prestation d&#039;animation musicale</option><option value="965">Prestation de cabaret</option><option value="966">Prestation de Campement</option><option value="949">Prestations d&#039;hébergement</option><option value="173">Prévention des risques majeurs</option><option value="1011">Prime liée aux activités laitières</option><option value="1144">Prix des médicaments</option><option value="990">Prix des produits</option><option value="223">Prix national de la qualité</option><option value="774">Procédure d&#039;AMM (autorisation de mise sur le marché )</option><option value="176">Procédures administratives</option><option value="177">Procédures administratives environnementales</option><option value="178">Procédures administratives SST</option><option value="842">Procédures d&#039;importation/ Exportation</option><option value="1112">Production</option><option value="1005">Production Primaire</option><option value="758">Produits de cacao et de chocolat</option><option value="759">Produits de la confiserie</option><option value="771">produits de la pêche</option><option value="964">Produits interdits</option><option value="872">Profession de médecin dentiste</option><option value="876">Profession de médecin vétérinaire</option><option value="1094">Professions médicales de libre pratique</option><option value="873">Professions pharmaceutiques</option><option value="142">Promotion de l&#039;emploi</option><option value="218">Promotion de l&#039;emploi / Apprentissage</option><option value="179">Propriété industrielle</option><option value="636">Propriété intellectuelle</option><option value="650">Propriété intellectuelle et industrielle</option><option value="181">Protection des travailleurs</option><option value="180">Protection du consommateur</option><option value="973">Protection Sociale</option><option value="806">Publicité</option><option value="233">Qualification du personnel</option><option value="934">Qualité de l&#039;eau</option><option value="1115">Radiothérapie</option><option value="227">Raffinage d’huiles</option><option value="182">Rayonnements ionisants</option><option value="1188">Registre national des entreprises</option><option value="1104">Réglementation Thermique des bâtiments</option><option value="171">Rejets atmosphériques</option><option value="211">Relation de travail</option><option value="153">Rémunération / Pension</option><option value="228">Rémunération / Prime</option><option value="185">Rémunération / SIVP</option><option value="186">Rémunération / SMIG</option><option value="214">Rémunération et avantages</option><option value="260">Rémunération/SMAG</option><option value="254">Représentation du personnel</option><option value="849">Responsabilité Sociétale de l&#039;Entreprise</option><option value="187">Ressources en eaux</option><option value="974">Restauration</option><option value="188">Risques biotechnologiques</option><option value="1173">Risques chimiques</option><option value="1172">Risques industriels / Prévention</option><option value="1175">Risques mécaniques</option><option value="1174">Risques physiques</option><option value="1062">Santé de l&#039;enfant</option><option value="1082">Santé Mentale</option><option value="1060">Secret professionnel et confidentialité</option><option value="192">Sécurité alimentaire</option><option value="193">Sécurité des équipements</option><option value="213">Sécurité Sociale</option><option value="1096">Service de Gardes médicales</option><option value="829">Service de médecine d&#039;urgence</option><option value="1058">Soins à l&#039;étranger</option><option value="691">SST Cameroun</option><option value="251">Stagiaires</option><option value="1049">Stérilisation</option><option value="1012">Stock de régulation</option><option value="580">Structures médicales</option><option value="1057">Stupéfiants et Psychotropes</option><option value="221">Sûreté et Gardiennage</option><option value="569">Surveillance des Médicaments</option><option value="88">Surveillance des produits</option><option value="216">Surveillance des produits / Additifs alimentaires</option><option value="217">Surveillance des produits / Intrants</option><option value="209">Surveillance des produits / Pesticides</option><option value="224">Surveillance des produits / produits de la pêche</option><option value="222">Surveillance des produits / Semences et plants</option><option value="194">Surveillance et mesurage</option><option value="972">Surveillance médicale</option><option value="195">Système National d&#039;Accréditation</option><option value="938">Tarifs</option><option value="944">Taxes</option><option value="624">télécommunications</option><option value="154">Temps de travail</option><option value="220">Temps de travail/ Heures supplémentaires</option><option value="197">Temps de travail/ jours fériés</option><option value="1002">Terres agricoles</option><option value="566">test airliquide</option><option value="967">Time-share</option><option value="951">Traçabilité</option><option value="1084">Transfusion Sanguine/ Hémovigilance</option><option value="1150">Transitaires</option><option value="557">Transparence</option><option value="198">Transport aérien</option><option value="1008">Transport Alimentaire</option><option value="199">Transport des marchandises</option><option value="1187">Transport des matières dangereuses</option><option value="200">Transport ferroviaire</option><option value="792">transport international</option><option value="257">Transport international / Denrées alimentaires</option><option value="120">Transport manuel de charges</option><option value="244">Transport maritime</option><option value="201">Transport naval</option><option value="202">Transport routier</option><option value="203">Transport sanitaire</option><option value="121">Travail à domicile</option><option value="212">Travail des enfants</option><option value="540">Travail des enfants et des jeunes</option><option value="909">Travail des femmes</option><option value="901">Travail des stagiaires</option><option value="196">Travail forcé / Contrat de travail</option><option value="205">Travail forcé / Vidéosurveillance</option><option value="207">Unités de mesure</option><option value="1125">Vaccins</option><option value="881">Vocation des terres</option><option value="208">Zone industrielle</option></select>
        </div>
                </div>
                      </div>
                  <div id="edit-field-text-type-tid-wrapper" class="views-exposed-widget views-widget-filter-field_text_type_tid">
                          <label for="edit-field-text-type-tid">
                    Type          </label>
                                <div class="views-widget">
                  <div class="form-item form-type-select form-item-field-text-type-tid">
         <select id="edit-field-text-type-tid" name="field_text_type_tid" class="form-select"><option value="All" selected="selected">- Tout -</option><option value="290">Accord</option><option value="263">Arrêté</option><option value="266">Avis</option><option value="285">Charte</option><option value="265">Circulaire</option><option value="1020">Code d&#039;usage</option><option value="272">Code de Conduite</option><option value="283">Code des eaux</option><option value="279">Code des hydrocarbures</option><option value="289">Code des ports maritimes</option><option value="287">Code disciplinaire et pénal maritime</option><option value="286">Code du commerce maritime</option><option value="274">Code du travail</option><option value="288">Code du travail maritime</option><option value="381">Code forestier</option><option value="275">Code Pénal</option><option value="575">Constitution.Tn</option><option value="270">Convention</option><option value="277">Dahir</option><option value="278">Décision</option><option value="264">Déclaration</option><option value="262">Décret</option><option value="620">Décret exécutif</option><option value="280">Décret-Loi</option><option value="268">Directive</option><option value="281">Document d&#039;orientation</option><option value="261">Loi</option><option value="267">Norme</option><option value="269">Pacte</option><option value="271">Protocole</option><option value="273">Recommandation</option><option value="276">Réglement</option></select>
        </div>
                </div>
                      </div>
                            <div class="views-exposed-widget views-submit-button">
              <input type="submit" id="edit-submit-general-text" name="" value="Valider" class="form-submit" />    </div>
              </div>
        </div>
        </div></form>    </div>


              <div class="view-content">
              <table class="views-table cols-13" >
                 <thead>
              <tr>

                  <th class="views-field views-field-text-stauts" >
                              </th>

                  <th class="views-field views-field-field-text-status" >
                    <a href="/admin/kv/text-base?title=&amp;field_text_status_value=All&amp;field_text_systeme_tid=All&amp;field_text_theme_tid=All&amp;field_text_type_tid=All&amp;order=field_text_status&amp;sort=asc" title="trier par Statut" class="active">Statut</a>          </th>

                  <th class="views-field views-field-text-penality" >
                    Pénalité encourue          </th>

                  <th class="views-field views-field-field-text-systeme" >
                    <a href="/admin/kv/text-base?title=&amp;field_text_status_value=All&amp;field_text_systeme_tid=All&amp;field_text_theme_tid=All&amp;field_text_type_tid=All&amp;order=field_text_systeme&amp;sort=asc" title="trier par Système" class="active">Système</a>          </th>

                  <th class="views-field views-field-field-text-theme" >
                    <a href="/admin/kv/text-base?title=&amp;field_text_status_value=All&amp;field_text_systeme_tid=All&amp;field_text_theme_tid=All&amp;field_text_type_tid=All&amp;order=field_text_theme&amp;sort=asc" title="trier par Thème" class="active">Thème</a>          </th>

                  <th class="views-field views-field-field-text-type" >
                    <a href="/admin/kv/text-base?title=&amp;field_text_status_value=All&amp;field_text_systeme_tid=All&amp;field_text_theme_tid=All&amp;field_text_type_tid=All&amp;order=field_text_type&amp;sort=asc" title="trier par Type" class="active">Type</a>          </th>

                  <th class="views-field views-field-title" >
                    <a href="/admin/kv/text-base?title=&amp;field_text_status_value=All&amp;field_text_systeme_tid=All&amp;field_text_theme_tid=All&amp;field_text_type_tid=All&amp;order=title&amp;sort=asc" title="trier par Titre" class="active">Titre</a>          </th>

                  <th class="views-field views-field-field-text-date-exigence" >
                    <a href="/admin/kv/text-base?title=&amp;field_text_status_value=All&amp;field_text_systeme_tid=All&amp;field_text_theme_tid=All&amp;field_text_type_tid=All&amp;order=field_text_date_exigence&amp;sort=asc" title="trier par Date exigence" class="active">Date exigence</a>          </th>

                  <th class="views-field views-field-field-text-piece-jointe-pdf" >
                    Pièces jointes          </th>

                  <th class="views-field views-field-field-text-abrogated" >
                    Abrogé(A), Abrogé partiellement(AP) Modifié(M), Complété(C)          </th>

                  <th class="views-field views-field-add-text" >
                    Action          </th>

                  <th class="views-field views-field-view-contact-form" >
                              </th>

                  <th class="views-field views-field-node-view-number" >
                    Vue          </th>
                      </tr>
            </thead>
            <tbody>
                  <tr class="odd views-row-first">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Env. - Securite Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Gestion des hydrocarbures          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret Présidentiel n° 2020-17 du 25 février 2020, portant ratification de la convention de garantie conclue le 16 septembre 2019, entre la République tunisienne et la Société islamique internationale de financement du commerce relative à la convention de morabaha conclue entre la Société tunisienne des industries de raffinage et la société précitée pour le financement des importations du pétrole brut et des produits pétroliers.<br /><span><a href="/admin/text-base/nojs/load/4219" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-02-25T00:00:00+01:00">25/02/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7372" title="Décret2020_017.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><div class="text-already-exist">exists</div></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-viewed">1</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Energie TN          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Energies Renouvelables          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret gouvernemental n ° 105 de 2020 du 25 février 2020, relatif abrogeant et complétant l&#039;arrêté gouvernemental n ° 1123 de 2016 du 24 août 2016 relatif à la fixation des conditions et modalités de réalisation des projets de production et de vente d&#039;électricité des énergies renouvelables<br /><span><a href="/admin/text-base/nojs/load/4205" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-02-25T00:00:00+01:00">25/02/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7333" title="Décret 25 février 2020.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                    (C) Décret gouvernemental n ° 105 de 2020 du 25 février 2020, relatif abrogeant et complétant l'arrêté gouvernemental n ° 1123 de 2016 du 24 août 2016 relatif à la fixation des conditions et modalités de réalisation des projets de production et de vente d'éle <br/> <br/>          </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><div class="text-already-exist">exists</div></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-viewed">6</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Construction &amp; Bâtiment          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret gouvernemental n ° 99 de 2020 du 17 février 2020 abrogeant et complétant le décret  n ° 2253 de 1999 du 11 octobre 1999 relative à l&#039;approbation des dispositions générales de reconstruction<br /><span><a href="/admin/text-base/nojs/load/4206" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-02-17T00:00:00+01:00">17/02/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7334" title="Décret 17 février 2020.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4206/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Rejets atmosphériques          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre des affaires locales et de l&#039;environnement du 7 février 2020, instituant un comité consultatif technique dans le domaine de l&#039;adaptation au changement climatique et contrôlant sa composition, ses pouvoirs et ses modalités de fonctionnement.<br /><span><a href="/admin/text-base/nojs/load/4203" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-02-07T00:00:00+01:00">07/02/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7319" title="Arrêté 7 février 2020.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><div class="text-already-exist">exists</div></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Rejets atmosphériques          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre des affaires locales et de l&#039;environnement du 7 février 2020, instituant un comité consultatif technique dans le domaine de l&#039;atténuation des émissions de gaz à effet de serre et fixant sa composition, ses pouvoirs et de ses modalités de fonctionnement.<br /><span><a href="/admin/text-base/nojs/load/4200" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-02-07T00:00:00+01:00">07/02/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7302" title="Arrêtédu 7 février 2020..pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4200/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Energie TN          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Energies Renouvelables          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre de l&#039;industrie et des petites et moyennes entreprises du 6 février 2020, portant approbation de la réalisation de projets de production d&#039;électricité à partir des énergies renouvelables à des fins d’autoconsommation raccordés au réseau national d’électricité haute et moyenne tension<br /><span><a href="/admin/text-base/nojs/load/4202" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-02-06T00:00:00+01:00">06/02/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7353" title="Arrêté2020_321.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4202/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Energie TN          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Production          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret gouvernemental n° 50 du 29 janvier 2020 portant sur la conclusion d’un accord entre le gouvernement de la République Tunisienne et le gouvernement de la République Italienne relatif à l’infrastructure de transport électrique dans le but d’évoluer les échanges d’énergies entre l’Europe et l’Afrique du Nord.<br /><span><a href="/admin/text-base/nojs/load/4166" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-01-29T00:00:00+01:00">29/01/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7248" title="Décret gouvernemental.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4166/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Energie TN          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Energies Renouvelables          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre de l&#039;industrie et des petites et moyennes entreprises du 20 janvier 2020, portant approbation de la mise en œuvre de projets de production d&#039;électricité à partir d&#039;énergies renouvelables à des fins d&#039;autoconsommation liées au réseau électrique national en moyenne tension.<br /><span><a href="/admin/text-base/nojs/load/4201" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-01-20T00:00:00+01:00">20/01/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7304" title="Arrêté du 20 janvier 2020.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4201/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Gestion des déchets et leur élimination          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret-gouvernemental  n°2020-32 de 2020 du 16 janvier 2020, relatif au contrôle des types de sacs en plastique dont la production, la fourniture, la distribution et la détention sur le marché intérieur sont interdites.<br /><span><a href="/admin/text-base/nojs/load/4150" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2020-01-16T00:00:00+01:00">16/01/2020</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7223" title="Décret2020_32Arabe.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4150/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Construction &amp; Bâtiment          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre de l’équipement, de l’habitat et de l’aménagement du territoire du 19 décembre 2019, modifiant l’arrêté du 17 avril 2007, portant définition des pièces constitutives du dossier du permis de bâtir, des délais, de validité, et prorogation et des conditions de son renouvellement.<br /><span><a href="/admin/text-base/nojs/load/4143" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-12-19T00:00:00+01:00">19/12/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7173" title="Arrêté du 19 décembre 2019.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4143/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                    <span class="new-icon">nouveau</span>          </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Construction &amp; Bâtiment          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret gouvernemental n°2019-1194 du 19 décembre 2019 modifiant le décret gouvernemental n° 2018-171 du 19 février 2018, portant promulgation de quelques règlements généraux de construction relatifs à l’équipement des constructions par des bâches de collecte et de stockage des eaux pluviales récupérées des terrasses des bâtiments non accessibles. <br /><span><a href="/admin/text-base/nojs/load/4142" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-12-19T00:00:00+01:00">19/12/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7174" title="Décret du 19 décembre 2019.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4142/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Métrologie Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Contrôle métrologique          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du Ministre du Commerce du 12 décembre 2019 relatif aux opérations de vérification et de poinçonnage des instruments de mesure au cours de l’année 2020<br /><span><a href="/admin/text-base/nojs/load/4124" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-12-12T00:00:00+01:00">12/12/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7144" title="A du 12-12-2019.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4124/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    SST - RS Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Accidents de travail &amp; maladies professionnelles          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret gouvernemental n°2019-1130 du 26 novembre 2019, portant ajustement des rentes alloués aux victimes des accidents du travail et des maladies professionnelles soumises aux dispositions de la Loi n° 94-28 du 21 février 1994, portant régime de réparation des préjudices résultant des accidents du travail et des maladies professionnelles<br /><span><a href="/admin/text-base/nojs/load/4112" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-11-26T00:00:00+01:00">26/11/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7121" title="DG2019-1130.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4112/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Env. - Securite Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Gestion des hydrocarbures          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre de l’industrie et des petites et moyennes entreprises du 21 novembre 2019 modifiant l’arrêté du 8 août 2009, fixant les conditions d’exploitation des réservoirs contenant des gaz inflammables liquéfiés. <br /><span><a href="/admin/text-base/nojs/load/4052" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-11-21T00:00:00+01:00">21/11/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7023" title="a du 21 nov 2019.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4052/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-viewed">1</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Vocation des terres          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre du développement, de l&#039;investissement et de la coopération internationale du 8 novembre 2019, est relatif à la commission des autorisations et agréments, fixant sa composition, les modalités et les modes de son fonctionnement, les délais d’octroi des autorisations ainsi que la liste des activités concernées.<br /><span><a href="/admin/text-base/nojs/load/4053" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-11-08T00:00:00+01:00">08/11/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7027" title="A du 08-11-2019.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4053/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Ressources en eaux          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre de l’agriculture, des ressources en eau et de la pêche et du ministre de développement, de l’investissement  et de coopération internationale du 04 novembre 2019, relatif au cahier des charges pour l’exercice de l&#039;activité de forage d&#039;eau.<br /><span><a href="/admin/text-base/nojs/load/4054" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-11-04T00:00:00+01:00">04/11/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/7028" title="A DU 4-11-2019.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/4054/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                    TS          </td>

                  <td class="views-field views-field-text-penality" >
                    <a href="/admin/text-base/nojs/load/penality/3947" class="ctools-use-modal ctools-modal-view-penality" title="Pop me up"><span class="penality-icon">1</span></a>          </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Logistique / SST Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Transport routier          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre du transport du 10 octobre 2019 modifiant l’arrêté du 19 août 2011, fixant le barème du montant de la transaction prévue par l&#039;article 47 de la loi n° 2004-33 du 19 avril 2004 portant organisation des transports terrestres.<br /><span><a href="/admin/text-base/nojs/load/3947" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-10-10T00:00:00+02:00">10/10/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/6806" title="Arrêté2019_4022Arabe.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/3947/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-viewed">1</span>          </td>
                      </tr>
                  <tr class="even">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                    TS          </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    SST - RS Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Accidents de travail &amp; maladies professionnelles          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du ministre des affaires sociales du 24 septembre 2019,  portant détermination des sièges et des compétences territoriales des commissions médicales habilitées à fixer le taux d’incapacité permanente de travail.<br /><span><a href="/admin/text-base/nojs/load/3948" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-09-24T00:00:00+02:00">24/09/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/6807" title="Arrêté2019-09-24.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/3948/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="odd">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Environnement Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Construction &amp; Bâtiment          </td>

                  <td class="views-field views-field-field-text-type" >
                    Arrêté          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Arrêté du Ministre de l&#039;équipement et de l&#039;habitat du 15 août 2019 complétant l&#039;Arrêté du 19 octobre 1995 déterminant la nature des travaux d&#039;aménagement préliminaires et des travaux définitifs du lotissement et le mode de leur réception.<br /><span><a href="/admin/text-base/nojs/load/3917" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-08-15T00:00:00+02:00">15/08/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/6728" title="A du 15-08-2019.pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/3917/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
                  <tr class="even views-row-last">

                  <td class="views-field views-field-text-stauts text-status" >
                              </td>

                  <td class="views-field views-field-field-text-status" >
                              </td>

                  <td class="views-field views-field-text-penality" >
                              </td>

                  <td class="views-field views-field-field-text-systeme" >
                    Env. - Securite Tn          </td>

                  <td class="views-field views-field-field-text-theme" >
                    Construction &amp; Bâtiment          </td>

                  <td class="views-field views-field-field-text-type" >
                    Décret          </td>

                  <td class="views-field views-field-title fix-width-50" >
                    Décret gouvernemental n°2019-487 du 15 août 2019, fixant l’organigramme de l’office national de la protection civile.<br /><span><a href="/admin/text-base/nojs/load/3899" class="ctools-use-modal ctools-modal-view-text" title="Pop me up">Résumé</a></span>          </td>

                  <td class="views-field views-field-field-text-date-exigence" >
                    <span class="date-display-single" property="dc:date" datatype="xsd:dateTime" content="2019-08-15T00:00:00+02:00">15/08/2019</span>          </td>

                  <td class="views-field views-field-field-text-piece-jointe-pdf" >
                    <a href="/download/file/fid/6710" title="D2019_757Arabe (1).pdf"><img class="file-icon" alt="" title="application/pdf" src="/modules/file/icons/application-pdf.png" /></a>          </td>

                  <td class="views-field views-field-field-text-abrogated" >
                              </td>

                  <td class="views-field views-field-add-text add-to-base-icon" >
                    <span><a href="https://keyveille.com/admin/text-base/nojs/3899/add?destination=admin/kv/text-base" class="ctools-use-modal ctools-modal-add-text" title="Ajouter">Ajouter</a></span>          </td>

                  <td class="views-field views-field-view-contact-form" >
                    <a href="/admin/kv/text-base/nojs/contact" class="ctools-use-modal ctools-modal-view-text" title="Formulaire de contact"><span class="contact-popin">Contact</span></a>          </td>

                  <td class="views-field views-field-node-view-number" >
                    <span class="kv-node-not-viewed">0</span>          </td>
                      </tr>
              </tbody>
        </table>    </div>

              <div class="pager clearfix"><ul class="links pager pager-list"><li class="1 pager-current first"><span>1</span></li>
        <li class="2 pager-item active"><a href="/admin/kv/text-base?page=1" title="Aller à la page 2" class="active">2</a></li>
        <li class="3 pager-item active"><a href="/admin/kv/text-base?page=2" title="Aller à la page 3" class="active">3</a></li>
        <li class="4 pager-item active"><a href="/admin/kv/text-base?page=3" title="Aller à la page 4" class="active">4</a></li>
        <li class="5 pager-item active"><a href="/admin/kv/text-base?page=4" title="Aller à la page 5" class="active">5</a></li>
        <li class="6 pager-item active"><a href="/admin/kv/text-base?page=5" title="Aller à la page 6" class="active">6</a></li>
        <li class="7 pager-item active"><a href="/admin/kv/text-base?page=6" title="Aller à la page 7" class="active">7</a></li>
        <li class="8 pager-item active"><a href="/admin/kv/text-base?page=7" title="Aller à la page 8" class="active">8</a></li>
        <li class="9 pager-item last active"><a href="/admin/kv/text-base?page=8" title="Aller à la page 9" class="active">9</a></li>
        </ul> <ul class="links pager pager-links"><li class="pager-next first active"><a href="/admin/kv/text-base?page=1" title="Aller à la page suivante" class="active">suivant ›</a></li>
        <li class="pager-last last active"><a href="/admin/kv/text-base?page=46" title="Aller à la dernière page" class="active">dernier »</a></li>
        </ul></div>




        </div>
        </div>

          </div>

          </div>
          </div>
        </div></div>

        <div id='footer' class='clearfix'>
          </div>
          <script type="text/javascript" src="https://keyveille.com/sites/all/modules/custom/kv_common/js/kv_common_ajax_view.js?pyk0jb"></script>
        </body>
        </html>


        HTML;
    }
}
