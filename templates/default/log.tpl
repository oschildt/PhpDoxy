<h1>{header_title}</h1>

<ul id="index">
<!-- if index_change_log -->
<li><a href="#change_log">Change log</a></li>
<!-- endif index_change_log -->

<!-- if index_deprecated -->
<li><a href="#deprecated">Deprecated</a></li>
<!-- endif index_deprecated -->

<!-- if index_todos -->
<li><a href="#todos">Todo</a></li>
<!-- endif index_todos -->
</ul>

<!-- if change_log -->
  <h1 id="change_log">Change log</h1>
      
      <!-- foreach change_log -->
          <hr>
          
          <h2>{object_title}</h2>
          
          <h3>Reference</h3>

          <p><a href="{object_link}">{object_full_name}</a></p>

          <!-- if object_since -->
          <h3>Change log</h3>

          <div class="item_list">
          <ul>
          <!-- foreach object_since -->
          <li>{object_version}<br>
          <!-- if object_description -->
          <span class="item_description">{object_description}</span></li>
          <!-- endif object_description -->
          <!-- endforeach object_since -->
          </ul>
          </div>
          <!-- endif object_since -->
      <!-- endforeach change_log -->
      
<!-- endif change_log -->

<!-- if deprecated -->
  <h1 id="deprecated">Deprecated</h1>
      
      <!-- foreach deprecated -->
          <hr>
          
          <h2>{object_title}</h2>
          
          <h3>Reference</h3>

          <p><a href="{object_link}">{object_full_name}</a></p>

          <!-- if object_deprecated -->
          <h3>Deprecated</h3>

          <div class="item_list">
          <ul>
          <!-- foreach object_deprecated -->
          <li>{object_version}<br>
          <!-- if object_description -->
          <span class="item_description">{object_description}</span></li>
          <!-- endif object_description -->
          <!-- endforeach object_deprecated -->
          </ul>
          </div>
          <!-- endif object_deprecated -->
      <!-- endforeach deprecated -->
      
<!-- endif deprecated -->

<!-- if todos -->
  <h1 id="todos">Todo</h1>
      
      <!-- foreach todos -->
          <hr>
          
          <h2>{object_title}</h2>
          
          <h3>Reference</h3>

          <p><a href="{object_link}">{object_full_name}</a></p>

          <!-- if object_todos -->
          <h3>Todo</h3>

          <div class="item_list">
          <ul>
          <!-- foreach object_todos -->
          <li>{object_description}</li>
          <!-- endforeach object_todos -->
          </ul>
          </div>
          <!-- endif object_todos -->
      <!-- endforeach todos -->
      
<!-- endif todos -->
