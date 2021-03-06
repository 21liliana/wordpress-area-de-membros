<?php
// Página de redirecionamento
function area_de_membros_redirect() {
	
	// Substitua o conteúdo desta variável pelo endereço da sua página de login.
	$redirect_page = 'https://www.modeloshostnet.com/lvt/';
	
	return $redirect_page;
}

// Regras de produtos de assinaturas de membros
function area_de_membros_produtos_regras() {
	
	// Configure aqui as suas regras de produtos para acesso a páginas e posts
	$products_members[] = array(
		'pages' => array(
			'productid'	=> 316,
			'pages' 	=> array( // Não utilize http:// ou https://
				'www.modeloshostnet.com/lvt/teste/teste-1/',
				'www.modeloshostnet.com/lvt/contact-us/'
			)
		),
		'parentpages' => array(
			'productid'	=> 316,
			'parents'	=> array(
				'teste',
				'teste1'
			)
		),
		'postcategories' => array(
			'productid'		=> 316,
			'categories'	=> array(
				'teste',
				'teste1'
			)
		)

	);
	
	return $products_members;
}

// Verifica se o usuário comprou o produto que permite acesso a esta página ou post
function area_de_membros_produtos() {
	global $post;

	// Variável que define se será necessário redirecionar para a página de login
	$redirect = false;
	
	// Página de redirecionamento
	$redirect_page = area_de_membros_redirect();

	// Regras de produtos de assinaturas de membros
	$products_members = area_de_membros_produtos_regras();
   
	// Processa as regras de acesso
	$necessary_products = array();
	foreach ($products_members as $product_rule) {
		
		// Verifica se tem regra de produtos para páginas específicas
		if (!empty($product_rule['pages'])) {
			if ($product_rule['pages']['productid'] > 0 
				and !empty($product_rule['pages']['pages']) ) {
				
				// URL completa da página acessada sem HTTP ou HTTPS
				$url = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				
				// Verifica se a página atual está nas regras de produtos
				foreach ($product_rule['pages']['pages'] as $page) {
					if ($url == $page) {
						$necessary_products[] = $product_rule['pages']['productid'];
					}
				}
			}
		}
		
		// Verifica se tem regra de produtos para páginas com ancestrais
		if (!empty($product_rule['parentpages']) and $post->post_type == 'page') {
			if ($product_rule['parentpages']['productid'] > 0 
				and !empty($product_rule['parentpages']['parents']) ) {
				
				// Pega os dados da página pai
				$post_parent = get_post($post->post_parent);
	
				// Pega o slug da página pai
				$parent_slug = $post_parent->post_name;
				
				// Verifica se a página atual tem um ancestral nas regras de produtos
				foreach ($product_rule['parentpages']['parents'] as $parent) {
					if ($parent_slug == $parent) {
						$necessary_products[] = $product_rule['parentpages']['productid'];
					}
				}
			}
		}
		
		// Verifica se tem regra de produtos para categorias de post
		if (!empty($product_rule['postcategories']) and $post->post_type == 'post') {
			if ($product_rule['postcategories']['productid'] > 0 
				and !empty($product_rule['postcategories']['categories']) ) {
				
				// Pega a lista de categorias do post
				$categories = get_the_category($post->ID);
				$postcategories = array();
				foreach($categories as $cat) {
					$postcategories[] = $cat->slug;
				}
				
				// Verifica se post atual tem uma categoria nas regras de produtos
				foreach ($product_rule['postcategories']['categories'] as $category) {
					if (array_search($category, $postcategories) !== false) {
						$necessary_products[] = $product_rule['postcategories']['productid'];
					}
				}
			}
		}
	}
	
	// Se a página ou post tiver um produto associado, verifica se o usuário comprou o produto
	if (!empty($necessary_products)) {
		
		// Força o redirecionamento
		$redirect = true;
		
		// Pega os produtos comprados pelo usuários
		$product_ids_by_curr_user = products_bought_by_curr_user();
		
		if (!empty($product_ids_by_curr_user)) {
			foreach ($product_ids_by_curr_user as $productid) {
				// Se o usuário comprou um dos produtos necessários para acessar
				// a página ou post, desativa o redirecionamento e sai do loop
				if (array_search($productid, $necessary_products) !== false) {
					$redirect = false;
					break;
				}
			}
		}
	}
	
	// Caso a página ou post tenha regras para produtos e o usuário
	// não tenha comprado um desses produtos, redireciona para a página de login
	if ($redirect) {
		wp_redirect( $redirect_page );
		exit;
	}   
}
add_action('template_redirect', 'area_de_membros_produtos');

// Coletas os produtos comprados pelo usuário
function products_bought_by_curr_user() {
	
	// Se o usuário não estiver logado
	if (!is_user_logged_in()) {
		return array();
	}
	
	// Dados do usuário
    $current_user = wp_get_current_user();
    if ( 0 == $current_user->ID ) return array();
   
    // Pedidos do usuário (Concluído + Processando)
	$order_statuses = array('wc-processing', 'wc-completed');
    $customer_orders = get_posts( array(
        'numberposts' => -1,
        'meta_key'    => '_customer_user',
        'meta_value'  => $current_user->ID,
        'post_type'   => wc_get_order_types(),
		'post_status' => $order_statuses,
    ) );
   
    // Loop dos pedidos para verifica se o usuário comprou o produto
    if ( ! $customer_orders ) return;
    $product_ids = array();
    foreach ( $customer_orders as $customer_order ) {
        $order = wc_get_order( $customer_order->ID );
        $items = $order->get_items();
        foreach ( $items as $item ) {
            $product_id 	= $item->get_product_id();
            $product_ids[] = $product_id;
        }
    }
    return array_unique($product_ids);
}

// Shortcode que exibe o conteúdo passado caso o usuário tenha
// comprado os produtos do parâmetro
//
// Exemplo:
// [areademembros products="1,2"]<p>Seu conteúdo</p>[/areademembros]
function area_de_membros_conteudo( $atts = array(), $content = null ) {
	
	// Estraindo parâmetros do shortcode
    extract(shortcode_atts(array(
     'products'			=> '',
	 'mensagem_erro'	=> ''
    ), $atts));

	// Extrai os IDs dos produtos
	$product_ids = explode( ",", $products );
	
	// Variável que retorna o conteúdo
	$show = false;
		
	if (!empty($product_ids)) {
		// Pega os produtos comprados pelo usuários
		$product_ids_by_curr_user = products_bought_by_curr_user();
		
		if (!empty($product_ids_by_curr_user)) {
			foreach ($product_ids_by_curr_user as $productid) {
				// Se o usuário comprou um dos produtos necessários para acessar
				// a página ou post, ativa a exibição de conteúdo e sai do loop
				if (array_search($productid, $product_ids) !== false) {
					$show = true;
					break;
				}
			}
		}
	}
	if ($show) {
		return $content;
	} else {
		return $mensagem_erro;
	}	
}
add_shortcode( 'areademembros', 'area_de_membros_conteudo' );
?>
