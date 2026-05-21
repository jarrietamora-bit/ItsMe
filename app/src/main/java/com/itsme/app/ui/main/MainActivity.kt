package com.itsme.app.ui.main
import android.content.Intent
import android.os.Bundle
import android.view.*
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModelProvider
import androidx.navigation.findNavController
import androidx.navigation.ui.*
import androidx.recyclerview.widget.LinearLayoutManager
import com.itsme.app.R
import com.itsme.app.databinding.ActivityMainBinding
import com.itsme.app.databinding.DialogAddUserBinding
import com.itsme.app.databinding.DialogUserManagementBinding
import com.itsme.app.ui.login.LoginActivity
import com.itsme.app.utils.SessionManager
import com.itsme.app.viewmodel.LoginViewModel
import com.itsme.app.viewmodel.TravelDateViewModel

class MainActivity : AppCompatActivity() {
    private lateinit var b: ActivityMainBinding
    private lateinit var tvmLogin: LoginViewModel
    private lateinit var tvmTravel: TravelDateViewModel

    override fun onCreate(s: Bundle?) {
        super.onCreate(s)
        b = ActivityMainBinding.inflate(layoutInflater); setContentView(b.root)
        setSupportActionBar(b.toolbar)
        tvmLogin = ViewModelProvider(this)[LoginViewModel::class.java]
        tvmTravel = ViewModelProvider(this)[TravelDateViewModel::class.java]

        tvmTravel.active.observe(this) { td ->
            supportActionBar?.subtitle = if (td != null) "Viaje: ${td.name}" else "Sin viaje activo"
            SessionManager.setTravelId(this, td?.id ?: 0)
        }

        val nav = findNavController(R.id.nav_host_fragment)
        val cfg = AppBarConfiguration(setOf(R.id.nav_clients, R.id.nav_articles, R.id.nav_wishlists, R.id.nav_travel_dates, R.id.nav_orders))
        setupActionBarWithNavController(nav, cfg)
        b.bottomNavigation.setupWithNavController(nav)
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.main_menu, menu)
        menu.findItem(R.id.action_users)?.isVisible = SessionManager.isAdmin(this)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem) = when (item.itemId) {
        R.id.action_users -> { showUserMgmt(); true }
        R.id.action_logout -> { SessionManager.logout(this); startActivity(Intent(this, LoginActivity::class.java)); finish(); true }
        else -> super.onOptionsItemSelected(item)
    }

    private fun showUserMgmt() {
        val vb = DialogUserManagementBinding.inflate(layoutInflater)
        vb.rvUsers.layoutManager = LinearLayoutManager(this)
        val adapter = UserAdapter { user ->
            AlertDialog.Builder(this).setTitle("Eliminar").setMessage("¿Eliminar ${user.username}?")
                .setPositiveButton("Eliminar") { _, _ -> tvmLogin.deleteUser(user) }
                .setNegativeButton("Cancelar", null).show()
        }
        vb.rvUsers.adapter = adapter
        tvmLogin.allUsers.observe(this) { adapter.submitList(it) }
        AlertDialog.Builder(this).setTitle("Usuarios").setView(vb.root)
            .setPositiveButton("Agregar") { _, _ -> showAddUser() }
            .setNegativeButton("Cerrar", null).show()
    }

    private fun showAddUser() {
        val vb = DialogAddUserBinding.inflate(layoutInflater)
        AlertDialog.Builder(this).setTitle("Nuevo Usuario").setView(vb.root)
            .setPositiveButton("Guardar") { _, _ ->
                val u = vb.etNewUsername.text.toString().trim()
                val p = vb.etNewPassword.text.toString().trim()
                if (u.isNotEmpty() && p.isNotEmpty()) tvmLogin.addUser(u, p, vb.switchAdmin.isChecked)
            }.setNegativeButton("Cancelar", null).show()
    }
}
