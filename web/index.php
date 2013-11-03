<!DOCTYPE html>
<html>
<head>
	<title>apigenerator.org - free apigen your sources!</title>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<!-- Bootstrap -->
	<link href="bootstrap/css/bootstrap.min.css" rel="stylesheet" media="screen">
	<link href="bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet" media="screen">
	<link href="style.min.css" rel="stylesheet" media="screen">
</head>
<body data-spy="scroll" data-target=".navbar" data-offset="1">
<div class="container-narrow">
	<a href="https://github.com/apigenerator/apigenerator.org" target="_blank" class="fork">
		<img src="https://s3.amazonaws.com/github/ribbons/forkme_left_red_aa0000.png" alt="Fork me on GitHub">
	</a>

	<header id="header">
		<div class="navbar">
  			<div class="navbar-inner">
				<a href="http://apigenerator.org" class="brand">apigenerator.org</a>
				<ul class="nav pull-right">
					<li><a href="#about"><i class="icon-home"></i> About</a></li>
					<li><a href="#use"><i class="icon-leaf"></i> Use</a></li>
					<li class="hide"><a href="#imprint"><i class="icon-user"></i> Imprint</a></li>
				</ul>
			</div>
		</div>
	</header>

	<section id="about">
		<h1><img src="logo.png"> free apigen your sources!</h1>
		<p class="lead">apigenerator.org creates your api documentation, using <a href="http://apigen.org" target="_blank">apigen</a>
			or <a href="http://phpdoc.org" target="_blank">phpDocumentor</a> just in time and push it back into your repository as github page!</p>
		<a href="#use" class="btn btn-large btn-success">It is free, try it out!</a>

		<!--
		<div id="github-badge"></div>
		<script type="text/javascript" charset="utf-8">
			GITHUB_USERNAME = 'apigenerator';
			GITHUB_LIST_LENGTH = 30;
			GITHUB_HEAD = 'div';
			GITHUB_THEME = 'white';
			GITHUB_TITLE = 'apigenerator.org';
			GITHUB_SHOW_ALL = 'Show all';
		</script>
		<script src="http://drnic.github.com/github-badges/dist/github-badge-launcher.js" type="text/javascript"></script>
		-->
	</section>

	<hr>

	<?php if (file_exists('history.html')): ?>
	<section id="history">
		<h2>Generation history</h2>
		<table class="table table-bordered table-striped">
			<thead>
			<tr>
				<th>date</th>
				<th>generator</th>
				<th>repository</th>
			</tr>
			</thead>
			<tbody>
			<?php include('history.html'); ?>
			</tbody>
		</table>
	</section>

	<hr>
	<?php endif; ?>

	<section id="use">
		<h2>How to use</h2>
		<p>Use apigenerator.org is really easy and done in three simple steps!</p>

		<ul class="nav nav-tabs">
			<li class="active"><a href="#apigen" data-toggle="tab">apigen</a></li>
			<li><a href="#phpdoc" data-toggle="tab">phpdoc</a></li>
		</ul>
		<div class="tab-content">
			<div class="tab-pane active" id="apigen">
				<h3>Step 1: create an apigen.yml in you project.</h3>
				<pre>~</pre>
				<p>Yes, if you want to use default settings, just add <code>~</code> to your <code>apigen.yml</code></p>
				<p class="alert alert-info">Have a look into
					<code><a href="https://github.com/apigenerator/apigenerator.org/blob/master/config/apigen.yml.dist" target="_blank">apigen.yml.dist</a></code>
					for all possible options.</p>
				<p>Our defaults are:</p>
				<pre># inline code examples typically contains tables or code snippets
allowed-html: b,i,a,ul,ol,li,p,br,var,samp,kbd,tt,table,thead,tbody,tr,td,pre,code
# we want to search everything!
autocomplete: classes,constants,functions,methods,properties,classconstants
# privates are useful to know for developers
access-levels: public,protected,private
# even internals are useful to know for developers
internal: yes
# the php lib is already documented on php.net
php: false
# the doc should contain deprecated to not differ from source
deprecated: true
# useful for developers AND users to know
todo: true</pre>
			</div>
			<div class="tab-pane" id="phpdoc">
				<h3>Step 1: create an phpdoc.yml in you project.</h3>
				<pre>~</pre>
				<p>Yes, if you want to use default settings, just add <code>~</code> to your <code>phpdoc.yml</code></p>
				<p class="alert alert-info">Have a look into
					<code><a href="https://github.com/apigenerator/apigenerator.org/blob/master/config/phpdoc.yml.dist" target="_blank">phpdoc.yml.dist</a></code>
					for all possible options.</p>
				<p>Our defaults are:</p>
				<pre># responsive ist the best template at the moment
template: responsive
# we think protected and privates are useful for developers
visibility: public,protected,private
parseprivate: true</pre>
			</div>
		</div>

		<hr>

		<h3>Step 2: Allow user apigenerator to push to your repository.</h3>

		<h4>for personal accounts</h4>
		<p>go to project Settings &rarr; Collaborators and add <code>apigenerator</code>.</p>

		<h4>for organizations without apigen team</h4>
		<p>go to organizations team page, create a new team called <code>apigen</code>, grant <em>Push &amp; Pull</em> access,
			add <code>apigenerator</code> as a member and the repositories you want to generate docs for.</p>

		<h4>for organizations with apigen team</h4>
		<p>go to project Settings &rarr; Teams and add the team <code>apigen</code>.</p>

		<p class="alert alert-danger"><strong>Security hint</strong>: We promise that we will use the access only to push the generated docs.
			If this is not enough for you, we have two solutions for you.<br></p>

		<p class="alert alert-success"><strong>Use a separate docs repository</strong>: You can use a separate repository for your docs,
			by adding <code>docs-repository: acme/docs</code> to your <code>apigen.yml</code> or <code>phpdoc.yml</code>.</p>

		<p class="alert alert-success"><strong>Install you own service</strong>: You can check out our code on
			<a href="https://github.com/apigenerator/apigenerator.org" target="_blank">github</a> and install your own
			apigenerator.org instance.</p>

		<hr>

		<h3>Step 3: Register a service hook.</h3>
		<p>Go to project Settings &rarr; Service Hooks &rarr; WebHook URLs and add <code>http://apigenerator.org/github.php</code></p>
		<p class="alert alert-info">We will provide a GitHub Service Hook shortly!</p>

		<h3>Step 4: Add badge to your README.md <span class="label label-info">optional</span></h3>
		<p><code>[![API DOCS](http://apigenerator.org/badge.png)](http://&lt;user&gt;.github.io/&lt;repo&gt;/)</code></p>
	</section>

	<hr>

	<footer id="imprint" class="text-center">
		<p>brought to you by <strong><a href="https://bit3.de/" target="_blank">bit3 UG</a></strong> &mdash; <a href="https://bit3.de/de/impressum.html" target="_blank">imprint</a></p>
	</footer>
</div>

<script src="http://code.jquery.com/jquery.js"></script>
<script src="bootstrap/js/bootstrap.js"></script>
</body>
</html>