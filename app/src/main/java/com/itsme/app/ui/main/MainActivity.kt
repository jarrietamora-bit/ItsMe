package com.itsme.app.ui.main

import android.content.Intent
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.ViewModelProvider
import androidx.navigation.fragment.NavHostFragment
import androidx.navigation.ui.AppBarConfiguration
import androidx.navigation.ui.setupActionBarWithNavController
import androidx.navigation.ui.setupWithNavController
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
    private lateinit var loginVm: LoginViewModel
    private lateinit var travelVm: TravelDateViewModel

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        b = ActivityMainBinding.inflate(layoutInflater)
        setContentView(b.root)
        setSupportActionBar(b.toolbar)

        loginVm = ViewModelProvider(this)[LoginViewModel::class.java]
        travelVm = ViewModelProvider(this)[TravelDateViewModel::class.java]

        travelVm.active.observe(this) { td ->
            supportActionBar?.subtitle = if (td != null) "Viaje: ${td.name}" else "Sin viaje activo"
            SessionManager.setTravelId(this, td?.id ?: 0)
        }

        val navHostFragment = supportFragmentManager
            .findFragmentById(R.id.nav_host_fragment) as NavHostFragment
        val navController = navHostFragment.navController

        val appBarConfig = AppBarConfiguration(
            setOf(R.id.nav_clients, R.id.nav_articles, R.id.nav_wishlists, R.id.nav_travel_dates, R.id.nav_orders)
        )
        setupActionBarWithNavController(navController, appBarConfig)
        b.bottomNavigation.setupWithNavController(navController)
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.main_menu, menu)
        menu.findItem(R.id.action_users)?.isVisible = SessionManager.isAdmin(this)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            R.id.action_users -> { showUserManagement(); true }
            R.id.action_logout -> {
                SessionManager.logout(this)
                startActivity(Intent(this, LoginActivity::class.java))
                finish()
                true
            }
            else -> super.onOptionsItemSelected(item)
        }
    }

    private fun showUserManagement() {
        val vb = DialogUserManagementBinding.inflate(layoutInflater)
        vb.rvUsers.layoutManager = LinearLayoutManager(this)
        val adapter = UserAdapter { user ->
            AlertDialog.Builder(this)
                .setTitle("Eliminar usuario")
                .setMessage("Eliminar ${user.username}?")
                .setPositiveButton("Eliminar") { _, _ -> loginVm.deleteUser(user) }
                .setNegativeButton("Cancelar", null)
                .show()
        }
        vb.rvUsers.adapter = adapter
        loginVm.allUsers.observe(this) { adapter.submitList(it) }
        AlertDialog.Builder(this)
            .setTitle("Usuarios")
            .setView(vb.root)
            .setPositiveButton("Agregar") { _, _ -> showAddUser() }
            .setNegativeButton("Cerrar", null)
            .show()
    }

    private fun showAddUser() {
        val vb = DialogAddUserBinding.inflate(layoutInflater)
        AlertDialog.Builder(this)
            .setTitle("Nuevo Usuario")
            .setView(vb.root)
            .setPositiveButton("Guardar") { _, _ ->
                val username = vb.etNewUsername.text.toString().trim()
                val password = vb.etNewPassword.text.toString().trim()
                if (username.isNotEmpty() && password.isNotEmpty()) {
                    loginVm.addUser(username, password, vb.switchAdmin.isChecked)
                }
            }
            .setNegativeButton("Cancelar", null)
            .show()
    }
}
