<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base: Visual";
$gOut["content"] = <<<EOF
			<div id="evidence_base_vis_container"> 
			
			<!--[if !IE]> --> 
				<object classid="java:evidence_base_vis.class" 
            			type="application/x-java-applet"
            			archive="evidence_base_vis.jar"
            			width="800" height="500"
            			standby="Loading Processing software..." > 
            			
					<param name="archive" value="evidence_base_vis.jar" /> 
				
					<param name="mayscript" value="true" /> 
					<param name="scriptable" value="true" /> 
				
					<param name="image" value="loading.gif" /> 
					<param name="boxmessage" value="Loading Processing software..." /> 
					<param name="boxbgcolor" value="#FFFFFF" /> 
				
					<param name="test_string" value="outer" /> 
			<!--<![endif]--> 
				
				<object classid="clsid:8AD9C840-044E-11D1-B3E9-00805F499D93" 
						codebase="http://java.sun.com/update/1.5.0/jinstall-1_5_0_15-windows-i586.cab"
						width="800" height="500"
						standby="Loading Processing software..."  > 
						
					<param name="code" value="evidence_base_vis" /> 
					<param name="archive" value="evidence_base_vis.jar" /> 
					
					<param name="mayscript" value="true" /> 
					<param name="scriptable" value="true" /> 
					
					<param name="image" value="loading.gif" /> 
					<param name="boxmessage" value="Loading Processing software..." /> 
					<param name="boxbgcolor" value="#FFFFFF" /> 
					
					<param name="test_string" value="inner" /> 
					
					<p> 
						<strong> 
							This browser does not have a Java Plug-in.
							<br /> 
							<a href="http://java.sun.com/products/plugin/downloads/index.html" title="Download Java Plug-in"> 
								Get the latest Java Plug-in here.
							</a> 
						</strong> 
					</p> 
				
				</object> 
				
			<!--[if !IE]> --> 
				</object> 
			<!--<![endif]--> 
			
			</div> 
EOF;

go();